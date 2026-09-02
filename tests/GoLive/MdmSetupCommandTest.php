<?php

declare(strict_types=1);

namespace App\Tests\GoLive;

use App\Account\Entity\User;
use App\GoLive\SousCommandeRunnerFactoryInterface;
use App\Pim\Entity\AeroportReference;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[Group('database')]
final class MdmSetupCommandTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingSousCommandeRunner $runner;

    /** @var list<string> fichiers d'import créés par le test, à supprimer */
    private array $fichiersCrees = [];

    /** @var array<string, array{env: ?string, server: ?string}> valeurs d'origine des variables surchargées */
    private array $envSauvegarde = [];

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->runner = new RecordingSousCommandeRunner();
        self::getContainer()->set(SousCommandeRunnerFactoryInterface::class, new RecordingSousCommandeRunnerFactory($this->runner));
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        $fs = new Filesystem();
        foreach ($this->fichiersCrees as $fichier) {
            $fs->remove($fichier);
        }
        foreach ($this->envSauvegarde as $nom => $origine) {
            if (null === $origine['env']) {
                unset($_ENV[$nom]);
            } else {
                $_ENV[$nom] = $origine['env'];
            }
            if (null === $origine['server']) {
                unset($_SERVER[$nom]);
            } else {
                $_SERVER[$nom] = $origine['server'];
            }
        }
        $this->envSauvegarde = [];
        parent::tearDown();
    }

    public function testLePreflightSeulNExecuteRien(): void
    {
        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([], $this->runner->appels);
        self::assertStringContainsString('Référentiel des sites de diffusion', $tester->getDisplay());
        // Le super admin absent est rapporté comme étape manuelle avec sa commande.
        self::assertStringContainsString('create-super-admin', $tester->getDisplay());
    }

    public function testLesEtapesDImportSontDerriereLOptionAvecImport(): void
    {
        $tester = $this->tester();

        $tester->execute([]);
        self::assertStringNotContainsString('Import des fiches Lieu', $tester->getDisplay());

        $tester->execute(['--avec-import' => true]);
        self::assertStringContainsString('Import des fiches Lieu', $tester->getDisplay());
        self::assertSame([], $this->runner->appels);
    }

    public function testExecuterIgnoreLesEtapesDejaFaites(): void
    {
        $this->entityManager->persist(new User('golive-setup@test.local', ['ROLE_SUPER_ADMIN']));
        $this->entityManager->persist(new AeroportReference('Aéroport de test', 'TST', 'FR', 48.0, 2.0));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $tester = $this->tester();
        $tester->execute(['--executer' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $commandes = $this->runner->commandes();
        // Le référentiel aéroports est peuplé : l'étape est ignorée.
        self::assertNotContains('app:acces:importer-aeroports', $commandes);
        // Les référentiels vides sont joués, le tableau de bord toujours recalculé en dernier.
        self::assertContains('app:acces:importer-grandes-villes', $commandes);
        self::assertContains('app:pim:importer-classements-atout-france', $commandes);
        self::assertSame('app:dashboard:recompute', end($commandes));
        // Le super admin (présent) et la planification des traductions restent hors orchestration.
        self::assertNotContains('app:user:create-super-admin', $commandes);
        self::assertNotContains('app:translations:schedule', $commandes);
    }

    public function testArretNetAuPremierEchec(): void
    {
        $this->runner->codesRetour['app:acces:importer-grandes-villes'] = 1;

        $tester = $this->tester();
        $tester->execute(['--executer' => true]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        $commandes = $this->runner->commandes();
        self::assertContains('app:acces:importer-grandes-villes', $commandes);
        self::assertNotContains('app:pim:importer-classements-atout-france', $commandes);
        self::assertNotContains('app:dashboard:recompute', $commandes);
        self::assertStringContainsString('Reprise', $tester->getDisplay());
    }

    public function testAvecImportDerouleLaChaineLegacyEtAvertitEnDebug(): void
    {
        // L'étape S3 est bloquante avec --avec-import : simuler des clés posées.
        $this->surchargerEnv('S3_ACCESS_KEY', 'cle-de-test');
        $this->surchargerEnv('S3_SECRET_KEY', 'secret-de-test');
        $this->deposerFichiersImport();

        $tester = $this->tester();
        $tester->execute(['--executer' => true, '--avec-import' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        // Le kernel de test est en debug : l'avertissement APP_DEBUG est affiché.
        self::assertStringContainsString('APP_DEBUG', $tester->getDisplay());
        $commandes = $this->runner->commandes();
        $ordreAttendu = [
            'app:legacy:import-lieux',
            'app:legacy:import-activites',
            'app:legacy:import-services',
            'app:legacy:import-restaurants',
            'app:legacy:import-photos', // semis
            'app:legacy:import-photos', // import
            'app:legacy:import-translations',
            'app:legacy:import-collaborateurs',
            'app:fiches:conformite-photos',
            'app:pim:attribuer-visibilite-geo',
            'app:localisation:normaliser',
            'app:pim:analyze-texts',
            'app:dam:analyze-media',
        ];
        self::assertSame($ordreAttendu, array_values(array_filter($commandes, static fn (string $c): bool => in_array($c, $ordreAttendu, true))));
        // Le fichier résolu est transmis explicitement, et le semis passe --seed-only.
        $parCommande = [];
        foreach ($this->runner->appels as $appel) {
            $parCommande[$appel['commande']][] = $appel['parametres'];
        }
        self::assertArrayHasKey('--file', $parCommande['app:legacy:import-lieux'][0]);
        self::assertSame(['--seed-only' => true], $parCommande['app:legacy:import-photos'][0]);
        self::assertSame(['--appliquer' => true], $parCommande['app:fiches:conformite-photos'][0]);
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:mdm:setup'));
    }

    private function surchargerEnv(string $nom, string $valeur): void
    {
        $this->envSauvegarde[$nom] ??= [
            'env' => isset($_ENV[$nom]) ? (string) $_ENV[$nom] : null,
            'server' => isset($_SERVER[$nom]) ? (string) $_SERVER[$nom] : null,
        ];
        $_ENV[$nom] = $valeur;
        $_SERVER[$nom] = $valeur;
    }

    private function deposerFichiersImport(): void
    {
        $projectDir = (string) self::getContainer()->getParameter('kernel.project_dir');
        $fs = new Filesystem();
        foreach ([
            'lists_infos_produits_v2_06-08-2026_02H24.csv',
            'dump-production.sql',
            'listes_fiches_produits_06-08-2026_17H31.xlsx',
        ] as $nom) {
            $chemin = $projectDir.'/var/import/'.$nom;
            if (!is_file($chemin)) {
                $fs->dumpFile($chemin, 'contenu factice pour test');
                $this->fichiersCrees[] = $chemin;
            }
        }
    }

    private function clear(): void
    {
        foreach (
            [
                'outbox_message',
                'etl_fiche_marketplace',
                'etl_legacy_photo',
                'etl_legacy_fiche',
                'enrichment_fiche_translation',
                'pim_fiche_collaborateur',
                'pim_ressource_lieu',
                'pim_lieu_administratif',
                'pim_lieu_tarification',
                'pim_lieu',
                'pim_activite',
                'pim_restaurant',
                'pim_service_evenementiel',
                'pim_fiche',
                'pim_localisation',
                'pim_aeroport_reference',
                'pim_grande_ville_reference',
                'pim_classement_atout_france',
                'account_password_reset_request',
                'account_invitation',
                'account_user',
            ] as $table
        ) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
