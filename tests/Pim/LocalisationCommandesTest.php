<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Service\BanClientInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class LocalisationCommandesTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testNormaliserReprendLeStockSansToucherLeWorkflow(): void
    {
        $id = $this->fiche(static function (Localisation $localisation): void {
            $localisation->changePays('France');
        });
        // Valeurs legacy injectées en SQL brut : l'entité normalise à
        // l'écriture, la reprise doit rattraper l'existant.
        $this->connection->executeStatement(
            'UPDATE pim_localisation SET region = ?, departement = ?, code_postal = ?',
            ['Île-De-France', 'ALPES MARITIMES', '6130'],
        );

        $tester = $this->tester('app:localisation:normaliser');
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $ligne = $this->connection->fetchAssociative('SELECT region, departement, code_postal FROM pim_localisation LIMIT 1');
        self::assertSame(['region' => 'Île-de-France', 'departement' => 'Alpes-Maritimes', 'code_postal' => '06130'], $ligne);
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
        // 06130 appartient bien aux Alpes-Maritimes : aucune incohérence signalée.
        self::assertStringNotContainsString('incohérent', $tester->getDisplay());
    }

    public function testVerifierEnrichitLesConformesEtTraceLePassage(): void
    {
        $id = $this->fiche(static function (Localisation $localisation): void {
            $localisation->changePays('France');
            $localisation->changeRuePostale('BP 24');
            $localisation->changeCodePostal('49590');
            $localisation->changeVille("FONTEVRAUD-L'ABBAYE");
        });
        $code = (int) $this->connection->fetchOne('SELECT code FROM pim_fiche');
        self::getContainer()->set(BanClientInterface::class, new FakeBanClient([
            (string) $code => [
                'score' => 0.93,
                'label' => "Fontevraud-l'Abbaye",
                'codePostal' => '49590',
                'ville' => "Fontevraud-l'Abbaye",
                'latitude' => '47.18',
                'longitude' => '0.05',
                'type' => 'municipality',
            ],
        ]));

        $tester = $this->tester('app:localisation:verifier');
        $tester->execute(['--appliquer' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $ligne = $this->connection->fetchAssociative('SELECT ville, latitude, longitude, ban_score, ban_verifie_le FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        // Recasage sur l'orthographe BAN (même clé de repli), GPS rempli, trace posée.
        self::assertSame("Fontevraud-l'Abbaye", $ligne['ville']);
        self::assertNotNull($ligne['latitude']);
        self::assertSame(0.93, (float) $ligne['ban_score']);
        self::assertNotNull($ligne['ban_verifie_le']);
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
    }

    public function testVerifierEnModeRapportNEcritRien(): void
    {
        $this->fiche(static function (Localisation $localisation): void {
            $localisation->changePays('France');
            $localisation->changeRuePostale('1 rue inconnue');
            $localisation->changeVille('Trifouillis');
        });
        self::getContainer()->set(BanClientInterface::class, new FakeBanClient([]));

        $tester = $this->tester('app:localisation:verifier');
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('aucune écriture', $tester->getDisplay());
        $ligne = $this->connection->fetchAssociative('SELECT ban_score, ban_verifie_le FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        self::assertNull($ligne['ban_score']);
        self::assertNull($ligne['ban_verifie_le']);
    }

    /** @param callable(Localisation): void $configurer */
    private function fiche(callable $configurer): string
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu des adresses');
        $localisation = new Localisation();
        $configurer($localisation);
        $lieu->changeLocalisation($localisation);
        $lieu->fiche()->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $lieu->fiche()->idString();
    }

    private function tester(string $commande): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find($commande));
    }

    private function clear(): void
    {
        foreach ([
            'outbox_message',
            'etl_fiche_marketplace',
            'pim_fiche_site_diffusion',
            'pim_fiche_attribute_value',
            'pim_ressource_lieu',
            'pim_acces_lieu',
            'pim_periode_fermeture',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
