<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Alimente le référentiel des sites de diffusion sans jamais supprimer une
 * entrée existante : les libellés, groupes, mentions et positions du catalogue
 * sont réappliqués, les sites inconnus du catalogue restent intacts.
 *
 * Catalogue métier réel (2026-08-13) : les 38 sites de la colonne
 * « Attribution visibilité » du XLSX production `listes_fiches_produits_*`
 * (libellés conservés à l'identique — l'import legacy des collaborateurs
 * résout les sites par libellé). Les groupes sont un rangement éditorial,
 * modifiables dans l'admin. « Business Profilers » est porté par le site
 * `marketplace_bp` existant : c'est la marketplace, son code déclenche la
 * synchronisation (MarketplaceSyncScheduler::SITE_CODE).
 */
#[AsCommand(name: 'app:sites-diffusion:sync', description: 'Synchronise le référentiel des sites de diffusion avec le catalogue embarqué.')]
final class SyncSitesDiffusionCommand extends Command
{
    /**
     * Catalogue : groupe → liste de [code, label, obligatoire, payant, présélectionné par défaut].
     *
     * @var array<string, list<array{string, string, bool, bool, bool}>>
     */
    private const CATALOGUE = [
        'Business Profilers' => [
            // 26 612 fiches sur 26 900 dans l'export production : présélectionné.
            [MarketplaceSyncScheduler::SITE_CODE, 'Business Profilers', false, false, true],
        ],
        'BPmeetings' => [
            ['bpmeetings_fr', 'BPmeetings FR', false, false, false],
            ['bpmeetings_de', 'BPmeetings DE', false, false, false],
            ['bpmeetings_es', 'BPmeetings ES', false, false, false],
            ['bpmeetings_uk', 'BPmeetings UK', false, false, false],
            ['bpmeetings_it', 'BPmeetings IT', false, false, false],
            ['bpmeetings_us', 'BPmeetings US', false, false, false],
            ['bpmeetings_nl', 'BPmeetings NL', false, false, false],
        ],
        'Guides & supports' => [
            ['hotel_seminaire', 'Hôtel & Séminaire', false, false, false],
            ['web_seminaire_business_france', 'WEB Séminaire Business France', false, false, false],
            ['guide_sbf', 'Guide SBF', false, false, false],
            ['vouchers', 'VOUCHERS', false, false, false],
        ],
        'Sites thématiques' => [
            ['seminaire_paris', 'Séminaire PARIS', false, false, false],
            ['seminaire_chateau', 'Séminaire CHATEAU', false, false, false],
            ['seminaire_mer', 'Séminaire MER', false, false, false],
            ['seminaire_paca', 'Séminaire PACA', false, false, false],
            ['destination_chantilly', 'Destination CHANTILLY', false, false, false],
            ['seminaire_montagne', 'Séminaire MONTAGNE', false, false, false],
            ['fontainebleau_seminaire', 'Fontainebleau Séminaire', false, false, false],
            ['seminaire_au_vert', 'Séminaire au vert', false, false, false],
        ],
        'Sites régionaux' => [
            ['bretagne', 'Bretagne', false, false, false],
            ['lyon', 'Lyon', false, false, false],
            ['strasbourg_colmar', 'Strasbourg / Colmar', false, false, false],
            ['nantes', 'Nantes', false, false, false],
            ['toulouse', 'Toulouse', false, false, false],
            ['lille', 'Lille', false, false, false],
            ['montpellier', 'Montpellier', false, false, false],
            ['annecy', 'Annecy', false, false, false],
            ['bordeaux', 'Bordeaux', false, false, false],
            ['biarritz', 'Biarritz', false, false, false],
            ['tours', 'Tours', false, false, false],
            ['corse', 'Corse', false, false, false],
            ['roissy_cdg', 'Roissy CDG', false, false, false],
            ['reims', 'Reims', false, false, false],
            ['versailles', 'Versailles', false, false, false],
            ['rennes', 'Rennes', false, false, false],
            ['normandie', 'Normandie', false, false, false],
            ['marne_la_vallee', 'Marne-la-Vallée', false, false, false],
        ],
    ];

    public function __construct(
        private readonly SiteDiffusionRepository $sites,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $toutesGammes = TypeFiche::cases();
        $created = 0;
        $updated = 0;
        $position = 0;

        foreach (self::CATALOGUE as $groupe => $entrees) {
            foreach ($entrees as [$code, $label, $obligatoire, $payant, $parDefaut]) {
                $gammes = $parDefaut ? $toutesGammes : [];
                $site = $this->sites->findOneByCode($code);
                if (!$site instanceof SiteDiffusion) {
                    $site = new SiteDiffusion($code, $label, $groupe, $obligatoire, $payant, $position, $gammes);
                    $this->entityManager->persist($site);
                    ++$created;
                } else {
                    $site->changeLabel($label);
                    $site->changeGroupe($groupe);
                    $site->changeObligatoire($obligatoire);
                    $site->changePayant($payant);
                    $site->changePosition($position);
                    $site->changeGammesParDefaut($gammes);
                    ++$updated;
                }
                ++$position;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf('Sites de diffusion synchronisés : %d créés, %d mis à jour.', $created, $updated));

        return Command::SUCCESS;
    }
}
