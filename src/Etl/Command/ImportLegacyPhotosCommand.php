<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Dam\Entity\MediaAsset;
use App\Dam\Service\FicheImageUploader;
use App\Dam\Service\MediaProcessingService;
use App\Etl\Entity\LegacyPhotoImport;
use App\Etl\Enum\LegacyPhotoStatus;
use App\Etl\Repository\LegacyFicheMappingRepository;
use App\Etl\Repository\LegacyPhotoImportRepository;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use App\Pim\Import\Legacy\LegacyPhotoCatalog;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Import manuel des photos legacy vers le DAM : upload S3 de l'original puis
 * génération des variantes INLINE (pas d'outbox — les workers consomment la
 * base de .env.local, pas forcément la base cible de l'import). Relançable :
 * chaque photo est suivie dans etl_legacy_photo, la reprise se fait par statut.
 *
 * Source des originaux : par défaut le dossier tmp/ à la racine du bucket S3
 * privé (déposé pour le déploiement, hors préfixe S3_PREFIX, supprimable après
 * import) ; --images-dir bascule sur une copie locale (dev).
 */
#[AsCommand(name: 'app:legacy:import-photos', description: 'Importe les photos legacy des fiches importées (S3 + variantes).')]
final class ImportLegacyPhotosCommand extends Command
{
    private const DEFAULT_S3_PREFIX = 'tmp';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegacyFicheMappingRepository $mappings,
        private readonly LegacyPhotoImportRepository $photos,
        private readonly LegacyPhotoCatalog $catalog,
        private readonly FicheImageUploader $ficheUploader,
        private readonly MediaProcessingService $processing,
        private readonly PrivateObjectStorageInterface $privateStorage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('images-dir', null, InputOption::VALUE_REQUIRED, 'Répertoire local des images legacy (sinon lecture depuis le bucket S3 privé).')
            ->addOption('images-s3-prefix', null, InputOption::VALUE_REQUIRED, 'Préfixe du bucket S3 privé où sont déposés les originaux.', self::DEFAULT_S3_PREFIX)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de photos à traiter.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots.', '25')
            ->addOption('retry-errors', null, InputOption::VALUE_NONE, 'Repasse les photos en erreur en attente avant de traiter.')
            ->addOption('syspad', null, InputOption::VALUE_REQUIRED, 'Ne traite que ce syspad_id (debug).')
            ->addOption('shard', null, InputOption::VALUE_REQUIRED, 'Partition i/n (ex. 0/4) pour paralléliser plusieurs exécutions.')
            ->addOption('seed-only', null, InputOption::VALUE_NONE, 'Décline uniquement photos_json en lignes de suivi, sans importer.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Contrôle la présence et la validité des fichiers sans rien écrire.')
            ->setHelp(<<<'HELP'
                Sans --shard, la commande sème le suivi (déclinaison de photos_json en
                lignes etl_legacy_photo) puis importe. Pour paralléliser, semer UNE SEULE
                fois avant de lancer les shards — deux semis concurrents violeraient
                l'unicité (syspad_id, legacy_path) :

                  bin/console app:legacy:import-photos --seed-only
                  bin/console app:legacy:import-photos --shard=0/4   # ×n terminaux, aucun semis
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $imagesDir = null === $input->getOption('images-dir') ? null : rtrim((string) $input->getOption('images-dir'), '/');
        $s3Prefix = trim((string) $input->getOption('images-s3-prefix'), '/');
        $limit = null === $input->getOption('limit') ? null : max(1, (int) $input->getOption('limit'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $syspad = null === $input->getOption('syspad') ? null : (int) $input->getOption('syspad');
        $dryRun = (bool) $input->getOption('dry-run');
        $seedOnly = (bool) $input->getOption('seed-only');
        [$shardIndex, $shardCount] = $this->parseShard($input->getOption('shard'));
        if ($seedOnly && null !== $shardIndex) {
            $io->error('--seed-only et --shard sont incompatibles : semer une seule fois avant de lancer les shards.');

            return Command::INVALID;
        }

        if (null !== $imagesDir && !is_dir($imagesDir)) {
            $io->error(sprintf('Répertoire des images legacy introuvable : %s', $imagesDir));

            return Command::INVALID;
        }
        if (null === $imagesDir && '' === $s3Prefix) {
            $io->error('Préfixe S3 vide : préciser --images-s3-prefix ou --images-dir.');

            return Command::INVALID;
        }
        $io->text(null === $imagesDir
            ? sprintf('Source des originaux : bucket S3 privé, préfixe « %s/ ».', $s3Prefix)
            : sprintf('Source des originaux : répertoire local %s.', $imagesDir));

        if ($dryRun) {
            return $this->dryRun($io, $imagesDir, $s3Prefix, $limit, $syspad);
        }

        // Jamais de semis en mode --shard : des shards concurrents sèmeraient
        // les mêmes mappings (violation d'unicité (syspad_id, legacy_path)).
        // Semer une seule fois via --seed-only (ou un lancement sans --shard).
        if (null === $shardIndex) {
            $seeded = $this->seed($io);
            if ($seeded > 0) {
                $io->text(sprintf('%d photos ajoutées au suivi.', $seeded));
            }
            if ($seedOnly) {
                $io->success(sprintf('Semis terminé (%d photos ajoutées au suivi). Lancer maintenant les shards.', $seeded));

                return Command::SUCCESS;
            }
        }
        if ($input->getOption('retry-errors')) {
            $retried = $this->photos->resetErrorsToPending();
            $io->text(sprintf('%d photos en erreur repassées en attente.', $retried));
        }

        $counters = ['traitées' => 0, 'importées' => 0, 'fichier absent' => 0, 'invalides' => 0, 'erreurs' => 0];
        $processedTotal = 0;
        while (null === $limit || $processedTotal < $limit) {
            $batchLimit = null === $limit ? $batchSize : min($batchSize, $limit - $processedTotal);
            $batch = $this->photos->findPending($batchLimit, $syspad, $shardIndex, $shardCount);
            if ([] === $batch) {
                break;
            }
            foreach ($batch as $photo) {
                ++$processedTotal;
                ++$counters['traitées'];
                $this->processOne($photo, $imagesDir, $s3Prefix, $counters);
                if (!$this->entityManager->isOpen()) {
                    $io->newLine();
                    $io->error('EntityManager fermé après une erreur SQL : arrêt. Relancer la commande (au besoin avec --retry-errors).');

                    return Command::FAILURE;
                }
                // Statut commité photo par photo : l'asset l'est déjà (flush
                // interne de processOne), un crash entre les deux laisserait
                // des photos "pending" déjà importées → doublons S3+DB à la
                // reprise.
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
            $io->write(sprintf("\r%d photos traitées (%d importées)…", $counters['traitées'], $counters['importées']));
        }
        $io->newLine();

        $io->table(['Compteur', 'Valeur'], array_map(null, array_keys($counters), array_values($counters)));
        $io->section('Répartition du suivi');
        $statuses = $this->photos->countByStatus();
        $io->table(['Statut', 'Total'], array_map(null, array_keys($statuses), array_values($statuses)));

        return 0 === $counters['erreurs'] ? Command::SUCCESS : Command::FAILURE;
    }

    /** Décline les photos_json non encore traités en lignes de suivi. */
    private function seed(SymfonyStyle $io): int
    {
        $seeded = 0;
        while ([] !== ($mappings = $this->mappings->findPhotosNotSeeded(200))) {
            foreach ($mappings as $mapping) {
                $result = $this->catalog->entries((string) $mapping->photosJson(), $mapping->gamme());
                foreach ($result['entries'] as $entry) {
                    $this->entityManager->persist(new LegacyPhotoImport($mapping->syspadId(), $entry['path'], $entry['category'], $entry['usage'], $entry['position']));
                    ++$seeded;
                }
                foreach ($result['skipped'] as $entry) {
                    $this->entityManager->persist(new LegacyPhotoImport($mapping->syspadId(), $entry['path'], $entry['category'], $entry['usage'], $entry['position'], LegacyPhotoStatus::SkippedLimit));
                    ++$seeded;
                }
                $mapping->markPhotosSeeded();
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
            $io->write(sprintf("\rDéclinaison du suivi photos : %d…", $seeded));
        }
        if ($seeded > 0) {
            $io->newLine();
        }

        return $seeded;
    }

    /**
     * Contrôle la présence (et en mode local la validité) des fichiers depuis
     * photos_json, sans écrire. En mode S3, seul exists() est vérifié : lire
     * chaque objet pour un getimagesize serait aussi coûteux que l'import.
     */
    private function dryRun(SymfonyStyle $io, ?string $imagesDir, string $s3Prefix, ?int $limit, ?int $syspad): int
    {
        $counters = ['contrôlées' => 0, 'valides' => 0, 'fichier absent' => 0, 'invalides' => 0, 'hors plafond (25)' => 0];
        $lastSyspad = -1;
        while (true) {
            $mappings = $this->mappings->createQueryBuilder('m')
                ->where('m.photosJson IS NOT NULL')
                ->andWhere('m.syspadId > :last')->setParameter('last', $lastSyspad)
                ->orderBy('m.syspadId', 'ASC')
                ->setMaxResults(200)
                ->getQuery()->getResult();
            if ([] === $mappings) {
                break;
            }
            foreach ($mappings as $mapping) {
                $lastSyspad = $mapping->syspadId();
                if (null !== $syspad && $mapping->syspadId() !== $syspad) {
                    continue;
                }
                $result = $this->catalog->entries((string) $mapping->photosJson(), $mapping->gamme());
                $counters['hors plafond (25)'] += count($result['skipped']);
                foreach ($result['entries'] as $entry) {
                    if (null !== $limit && $counters['contrôlées'] >= $limit) {
                        break 3;
                    }
                    ++$counters['contrôlées'];
                    if (null === $imagesDir) {
                        if ($this->privateStorage->exists($s3Prefix.'/'.$entry['path'])) {
                            ++$counters['valides'];
                        } else {
                            ++$counters['fichier absent'];
                        }
                        continue;
                    }
                    $path = $imagesDir.'/'.$entry['path'];
                    if (!is_file($path)) {
                        ++$counters['fichier absent'];
                        continue;
                    }
                    $image = @getimagesize($path);
                    if (!is_array($image) || $image[0] < 960 || $image[1] < 480) {
                        ++$counters['invalides'];
                    } else {
                        ++$counters['valides'];
                    }
                }
            }
            $this->entityManager->clear();
            $io->write(sprintf("\r%d photos contrôlées…", $counters['contrôlées']));
        }
        $io->newLine();
        $io->table(['Compteur', 'Valeur'], array_map(null, array_keys($counters), array_values($counters)));
        $io->note('Dry-run : aucune écriture effectuée.');

        return Command::SUCCESS;
    }

    /** @param array<string, int> $counters */
    private function processOne(LegacyPhotoImport $photo, ?string $imagesDir, string $s3Prefix, array &$counters): void
    {
        $temporaryFile = null;
        if (null !== $imagesDir) {
            $path = $imagesDir.'/'.$photo->legacyPath();
            if (!is_file($path)) {
                ++$counters['fichier absent'];
                $photo->markFailed(LegacyPhotoStatus::MissingFile, sprintf('Fichier absent : %s', $photo->legacyPath()));

                return;
            }
        } else {
            $key = $s3Prefix.'/'.$photo->legacyPath();
            if (!$this->privateStorage->exists($key)) {
                ++$counters['fichier absent'];
                $photo->markFailed(LegacyPhotoStatus::MissingFile, sprintf('Objet S3 absent : %s', $key));

                return;
            }
            try {
                $path = $temporaryFile = $this->downloadToTemporaryFile($key, $photo->legacyPath());
            } catch (\Throwable $exception) {
                ++$counters['erreurs'];
                $photo->markFailed(LegacyPhotoStatus::Error, sprintf('Lecture S3 impossible (%s) : %s', $key, $exception->getMessage()));

                return;
            }
        }

        try {
            $this->importOne($photo, $path, $counters);
        } finally {
            if (null !== $temporaryFile) {
                @unlink($temporaryFile);
            }
        }
    }

    /** @param array<string, int> $counters */
    private function importOne(LegacyPhotoImport $photo, string $path, array &$counters): void
    {
        $mapping = $this->mappings->find($photo->syspadId());
        if (null === $mapping) {
            ++$counters['erreurs'];
            $photo->markFailed(LegacyPhotoStatus::Error, 'Fiche introuvable pour ce syspad.');

            return;
        }
        $ficheEntityClass = match ($mapping->gamme()) {
            LegacyPhotoCatalog::GAMME_ACTIVITE => Activite::class,
            LegacyPhotoCatalog::GAMME_SERVICE => ServiceEvenementiel::class,
            LegacyPhotoCatalog::GAMME_RESTAURANT => Restaurant::class,
            default => null,
        };

        $asset = null;
        $resource = null;
        $owner = null;
        $fiche = null;
        $flushed = false;
        try {
            $file = new UploadedFile($path, basename($photo->legacyPath()), null, null, true);
            if (null !== $ficheEntityClass) {
                $entity = $this->entityManager->find($ficheEntityClass, (string) $mapping->ficheId());
                if (!$entity instanceof Activite && !$entity instanceof ServiceEvenementiel && !$entity instanceof Restaurant) {
                    throw new \RuntimeException('Fiche introuvable pour ce syspad.');
                }
                $asset = $this->ficheUploader->upload($file, $entity->fiche());
                $fiche = $entity->fiche();
                $owner = $entity;
            } else {
                $lieu = $this->entityManager->find(Lieu::class, (string) $mapping->ficheId());
                if (!$lieu instanceof Lieu) {
                    throw new \RuntimeException('Lieu introuvable pour ce syspad.');
                }
                $asset = $this->ficheUploader->upload($file, $lieu->fiche());
                $fiche = $lieu->fiche();
                $owner = $lieu;
            }
            $resource = $this->resource($asset->id(), $photo);
            // preserveWorkflowDuring englobe le flush : addRessource passe par
            // Lieu::touch → fiche->markChanged, ce qui repasserait une fiche
            // publiée en brouillon pendant un simple import.
            $fiche->preserveWorkflowDuring(function () use ($owner, $resource, $asset): void {
                $owner->addRessource($resource);
                $this->entityManager->persist($asset);
                // La ressource doit être en base avant la génération des
                // variantes (crop/rotation relus depuis la ressource).
                $this->entityManager->flush();
            });
            $flushed = true;
            $this->processing->process($asset);
            $photo->markDone($asset->id());
            ++$counters['importées'];
        } catch (\DomainException $exception) {
            ++$counters['invalides'];
            $photo->markFailed(LegacyPhotoStatus::Invalid, $exception->getMessage());
            $this->rollbackAsset($asset, $resource, $owner, $fiche, $flushed);
        } catch (\Throwable $exception) {
            ++$counters['erreurs'];
            $photo->markFailed(LegacyPhotoStatus::Error, $exception->getMessage());
            $this->rollbackAsset($asset, $resource, $owner, $fiche, $flushed);
        }
    }

    /**
     * Défait un import partiel : si l'asset et la ressource ont été commités
     * avant l'échec (génération des variantes), leurs lignes doivent aussi
     * être supprimées — sinon la fiche garde un média cassé et --retry-errors
     * crée un doublon au rejeu.
     */
    private function rollbackAsset(
        ?MediaAsset $asset,
        ?RessourceLieu $resource,
        Activite|ServiceEvenementiel|Restaurant|Lieu|null $owner,
        ?Fiche $fiche,
        bool $flushed,
    ): void {
        if (null === $asset) {
            return;
        }
        if ($flushed && $this->entityManager->isOpen() && null !== $resource && null !== $owner && null !== $fiche) {
            // orphanRemoval sur la collection : retirer la ressource suffit à
            // supprimer sa ligne. Même neutralisation du workflow que l'écriture.
            $fiche->preserveWorkflowDuring(function () use ($owner, $resource, $asset): void {
                $owner->removeRessource($resource);
                $this->entityManager->remove($asset);
                $this->entityManager->flush();
            });
        }
        $this->ficheUploader->delete($asset);
    }

    /**
     * Copie un original du bucket S3 privé vers un fichier temporaire local,
     * en conservant l'extension d'origine (getimagesize et validations MIME
     * de l'uploader s'appuient sur le contenu, mais le nom sert de repère).
     */
    private function downloadToTemporaryFile(string $key, string $legacyPath): string
    {
        $base = tempnam(sys_get_temp_dir(), 'legacy-photo-');
        if (false === $base) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.');
        }
        $extension = strtolower(pathinfo($legacyPath, \PATHINFO_EXTENSION));
        $path = '' === $extension ? $base : $base.'.'.$extension;

        $source = $this->privateStorage->readStream($key);
        $destination = fopen($path, 'wb');
        if (false === $destination) {
            @unlink($base);
            throw new \RuntimeException(sprintf('Impossible d\'écrire le fichier temporaire %s.', $path));
        }
        try {
            stream_copy_to_stream($source, $destination);
        } finally {
            fclose($destination);
            if (is_resource($source)) {
                fclose($source);
            }
            if ($path !== $base) {
                @unlink($base);
            }
        }

        return $path;
    }

    private function resource(string $assetId, LegacyPhotoImport $photo): RessourceLieu
    {
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($assetId);
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage($photo->usageCode());
        $resource->changePosition($photo->position());

        return $resource;
    }

    /** @return array{0: ?int, 1: ?int} */
    private function parseShard(mixed $shard): array
    {
        if (null === $shard) {
            return [null, null];
        }
        if (1 !== preg_match('#^(\d+)/(\d+)$#', (string) $shard, $matches) || (int) $matches[2] < 1 || (int) $matches[1] >= (int) $matches[2]) {
            throw new \InvalidArgumentException('Format de --shard invalide, attendu i/n avec i < n.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
