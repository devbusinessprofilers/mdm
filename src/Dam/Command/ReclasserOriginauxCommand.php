<?php

declare(strict_types=1);

namespace App\Dam\Command;

use App\Dam\Entity\MediaAsset;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\FicheImageUploader;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;

/**
 * Range les photos sous le segment de leur gamme dans le bucket.
 *
 * Deux uploaders ont longtemps coexisté : l'éditeur rangeait toute photo sous
 * `lieux/`, l'API et l'import sous le segment de la gamme (`restaurants/`,
 * `activites/`, `services/`). Pour chaque photo mal rangée, la commande
 * recopie l'original, la retouche éventuelle et les rendus sous le bon
 * segment, met à jour les clés en base et replanifie la synchronisation
 * marketplace de la fiche (le payload porte les clés des rendus).
 *
 * Sans option : rapport seul. `--appliquer` recopie et met à jour ; les
 * anciens objets restent en place tant que `--purger` n'est pas passé, pour
 * laisser la marketplace et le CDN basculer sur les nouvelles clés.
 */
#[AsCommand(name: 'app:dam:reclasser-originaux', description: 'Range originaux et rendus des photos sous le segment de leur gamme (rapport seul sans --appliquer).')]
final class ReclasserOriginauxCommand extends Command
{
    private const LOT = 200;

    public function __construct(
        private readonly RessourceLieuRepository $ressources,
        private readonly MediaAssetRepository $assets,
        private readonly EntityManagerInterface $entityManager,
        private readonly PrivateObjectStorageInterface $privateStorage,
        private readonly PublicObjectStorageInterface $publicStorage,
        private readonly OutboxPublisherInterface $outbox,
        #[Autowire('%env(S3_PREFIX)%')]
        private readonly string $storagePrefix,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('appliquer', null, InputOption::VALUE_NONE, 'Recopie les objets, met à jour les clés et replanifie la synchronisation marketplace')
            ->addOption('purger', null, InputOption::VALUE_NONE, 'Supprime les anciens objets une fois recopiés (avec --appliquer)')
            ->addOption('limite', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de photos à reclasser (0 = toutes)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appliquer = (bool) $input->getOption('appliquer');
        $purger = (bool) $input->getOption('purger');
        $limite = max(0, (int) $input->getOption('limite'));
        if ($purger && !$appliquer) {
            $io->error('--purger ne va pas sans --appliquer.');

            return Command::INVALID;
        }

        $prefix = trim($this->storagePrefix, '/');
        $base = '' === $prefix ? '' : $prefix.'/';

        $lignes = $this->ressources->photosAvecGamme();
        $attendu = [];
        $fiches = [];
        foreach ($lignes as $ligne) {
            $attendu[$ligne['asset']] = FicheImageUploader::segment(TypeFiche::from($ligne['type']));
            $fiches[$ligne['asset']] = (string) Ulid::fromBinary($ligne['fiche_id']);
        }

        // Balayage en SQL brut : deux requêtes, aucune entité chargée. Seuls
        // les candidats sont ensuite chargés par lots pour être reclassés.
        /** @var array<string, int> $parSegment */
        $parSegment = [];
        $horsPrefixe = 0;
        $candidats = [];
        $segments = [];
        foreach ($this->assets->clesOriginaux() as $id => $cle) {
            $nouveau = $attendu[$id] ?? null;
            if (null === $nouveau) {
                continue;
            }
            if (!str_starts_with($cle, $base)) {
                ++$horsPrefixe;
                continue;
            }
            $ancien = explode('/', substr($cle, strlen($base)), 2)[0];
            if ('' === $ancien || $ancien === $nouveau) {
                continue;
            }
            if (0 !== $limite && count($candidats) >= $limite) {
                break;
            }
            $libelle = $ancien.' → '.$nouveau;
            $parSegment[$libelle] = ($parSegment[$libelle] ?? 0) + 1;
            $candidats[] = $id;
            $segments[$id] = [$ancien, $nouveau];
        }
        $reclassees = count($candidats);

        $aResynchroniser = [];
        $anciennesCles = ['private' => [], 'public' => []];
        if ($appliquer) {
            foreach (array_chunk($candidats, self::LOT) as $ids) {
                foreach ($this->assets->findByStringIds($ids) as $asset) {
                    [$ancien, $nouveau] = $segments[$asset->id()];
                    $anciennes = $this->reclasser($asset, $base, $ancien, $nouveau);
                    $anciennesCles['private'] = [...$anciennesCles['private'], ...$anciennes['private']];
                    $anciennesCles['public'] = [...$anciennesCles['public'], ...$anciennes['public']];
                    $aResynchroniser[$fiches[$asset->id()]] = true;
                }
                foreach (array_keys($aResynchroniser) as $ficheId) {
                    $this->outbox->enqueue(new IndexFiche($ficheId));
                }
                $aResynchroniser = [];
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        if ($purger) {
            foreach ($anciennesCles['private'] as $cle) {
                $this->privateStorage->delete($cle);
            }
            foreach ($anciennesCles['public'] as $cle) {
                $this->publicStorage->delete($cle);
            }
        }

        $io->title($appliquer ? 'Photos reclassées' : 'Photos à reclasser (rapport, rien n’a été modifié)');
        $io->table(
            ['Segment', 'Photos'],
            array_map(static fn (string $libelle, int $photos): array => [$libelle, $photos], array_keys($parSegment), $parSegment),
        );
        if ($horsPrefixe > 0) {
            $io->warning(sprintf('%d photo(s) hors du préfixe « %s » ignorée(s).', $horsPrefixe, $prefix));
        }
        if (!$appliquer && $reclassees > 0) {
            $io->note('Relancer avec --appliquer pour recopier, puis --purger pour supprimer les anciens objets.');
        }
        if ($appliquer && !$purger && $reclassees > 0) {
            $io->note(sprintf('%d ancien(s) objet(s) conservé(s) ; relancer avec --appliquer --purger une fois la marketplace resynchronisée.', count($anciennesCles['private']) + count($anciennesCles['public'])));
        }
        $io->success(sprintf('%d photo(s) %s.', $reclassees, $appliquer ? 'reclassée(s)' : 'à reclasser'));

        return Command::SUCCESS;
    }

    /**
     * Recopie original, retouche et rendus sous le nouveau segment et met à
     * jour les clés de l'asset. Idempotent : un objet déjà présent à la
     * nouvelle clé n'est pas recopié.
     *
     * @return array{private: list<string>, public: list<string>} anciennes clés, à purger plus tard
     */
    private function reclasser(MediaAsset $asset, string $base, string $ancien, string $nouveau): array
    {
        $anciennes = ['private' => [], 'public' => []];

        $original = $asset->originalStorageKey();
        $nouvelOriginal = $base.$nouveau.substr($original, strlen($base.$ancien));
        $this->copierPrive($original, $nouvelOriginal);
        $anciennes['private'][] = $original;

        $retouche = $asset->enhancedStorageKey();
        $nouvelleRetouche = $retouche;
        if (null !== $retouche && str_starts_with($retouche, $base.$ancien.'/')) {
            $nouvelleRetouche = $base.$nouveau.substr($retouche, strlen($base.$ancien));
            $this->copierPrive($retouche, $nouvelleRetouche);
            $anciennes['private'][] = $retouche;
        }
        $asset->relocate($nouvelOriginal, $nouvelleRetouche);

        // Rendus : {base}photos/{variante}/{segment}/… — seul le segment change.
        $motif = '#^('.preg_quote($base.'photos/', '#').'[^/]+/)'.preg_quote($ancien, '#').'/#';
        foreach ($asset->renditions() as $rendition) {
            $cle = $rendition->storageKey();
            $nouvelle = preg_replace($motif, '${1}'.$nouveau.'/', $cle, 1);
            if (null === $nouvelle || $nouvelle === $cle) {
                continue;
            }
            if (!$this->publicStorage->exists($nouvelle)) {
                $this->publicStorage->write($nouvelle, $this->publicStorage->read($cle), [
                    'visibility' => 'public',
                    'ContentType' => $rendition->mimeType(),
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
            }
            $rendition->relocate($nouvelle);
            $anciennes['public'][] = $cle;
        }

        return $anciennes;
    }

    private function copierPrive(string $de, string $vers): void
    {
        if ($this->privateStorage->exists($vers)) {
            return;
        }
        $flux = $this->privateStorage->readStream($de);
        try {
            $this->privateStorage->writeStream($vers, $flux, ['visibility' => 'private']);
        } finally {
            if (is_resource($flux)) {
                fclose($flux);
            }
        }
    }
}
