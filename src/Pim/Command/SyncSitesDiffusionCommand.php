<?php

declare(strict_types=1);

namespace App\Pim\Command;

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
 * ⚠️ Le catalogue ci-dessous est PROVISOIRE : il reprend la liste de la
 * maquette front (test-integration) en attendant la liste métier réelle.
 * Remplacer les entrées puis relancer la commande suffira.
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
        'Réseau Business Profilers' => [
            ['BUSINESS_PROFILERS', 'Business Profilers', true, false, false],
            ['BP_LIEUX', 'BP Lieux', false, false, true],
            ['BP_SEMINAIRES', 'BP Séminaires', false, false, true],
            ['BP_EVENEMENTS', 'BP Événements', false, false, false],
        ],
        'Partenaires MICE' => [
            ['BEDOUK', 'Bedouk', false, false, true],
            ['ABC_SALLES', 'ABC Salles', false, false, false],
            ['1001_SALLES', '1001 Salles', false, false, false],
            ['SEMINAIRE_COM', 'Séminaire.com', false, false, true],
            ['KACTUS', 'Kactus', false, false, true],
            ['EVENTMAKER', 'Eventmaker', false, false, false],
            ['MEETINGPACKAGE', 'MeetingPackage', false, false, false],
            ['CVENT', 'Cvent', false, false, false],
            ['VENUEDIRECTORY', 'VenueDirectory', false, false, false],
        ],
        'Régies & médias' => [
            ['FIGARO_EVENEMENTS', 'Le Figaro Événements', false, true, true],
            ['ECHOS_LE_PARISIEN', 'Les Échos Le Parisien', false, true, false],
            ['CHALLENGES', 'Challenges', false, false, false],
            ['STRATEGIES', 'Stratégies', false, false, false],
            ['VOYAGES_AFFAIRES', "Voyages d'Affaires", false, false, false],
            ['DEPLACEMENTS_PROS', 'Déplacements Pros', false, false, false],
            ['ECHO_TOURISTIQUE', "L'Écho Touristique", false, false, false],
            ['TENDANCE_HOTELLERIE', 'Tendance Hôtellerie', false, false, false],
        ],
        'International' => [
            ['TRIVAGO_BUSINESS', 'Trivago Business', false, false, false],
            ['BOOKING_MEETINGS', 'Booking Meetings', false, false, false],
            ['HRS_MEETINGS', 'HRS Meetings', false, false, false],
            ['CITUATION', 'Cituation', false, false, false],
            ['VENUU', 'Venuu', false, false, false],
            ['TAGVENUE', 'Tagvenue', false, false, true],
            ['VENUESCANNER', 'VenueScanner', false, false, false],
            ['SPACEBASE', 'Spacebase', false, false, false],
            ['EVENTUP', 'EventUp', false, false, false],
            ['HIRE_SPACE', 'Hire Space', false, false, false],
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
        $io->note('Catalogue provisoire issu de la maquette — à remplacer par la liste métier.');

        return Command::SUCCESS;
    }
}
