<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class ImportLegacyActivitesCommandTest extends KernelTestCase
{
    private const TABLES = ['etl_legacy_photo', 'etl_legacy_fiche', 'pim_activite_offre', 'pim_activite', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message'];

    private Connection $connection;
    private ?string $csvFile = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->cleanTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanTables();
        }
        if (null !== $this->csvFile) {
            @unlink($this->csvFile);
        }

        parent::tearDown();
    }

    public function testImportCreatesActivitesIdempotently(): void
    {
        $tester = $this->tester();
        $file = $this->writeSampleCsv();

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE type = 'activite'"));
        self::assertSame(4200, (int) $this->connection->fetchOne("SELECT f.code FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 4200"));
        self::assertSame('publiee', $this->connection->fetchOne("SELECT f.status FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 4200"));
        self::assertSame('en_cours', $this->connection->fetchOne("SELECT f.status FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 4300"));
        self::assertSame('mobile', $this->connection->fetchOne('SELECT a.mode_intervention FROM pim_activite a JOIN etl_legacy_fiche m ON m.fiche_id = a.id WHERE m.syspad_id = 4200'));
        self::assertSame('Idée', $this->connection->fetchOne('SELECT gamme FROM etl_legacy_fiche WHERE syspad_id = 4200'));

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('déjà importées', $tester->getDisplay());
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE type = 'activite'"));
    }

    public function testDryRunWritesNothing(): void
    {
        $tester = $this->tester();
        $tester->execute(['--file' => $this->writeSampleCsv(), '--dry-run' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:legacy:import-activites'));
    }

    private function writeSampleCsv(): string
    {
        $headers = ['Id syspad', 'Publié / non publié', 'Nom Français', 'Gamme', "Type d'activités", "Objectifs de l'activité", 'Temps minimum', 'Temps maximum', 'Tarifs activité à partir de', "Rayon d'action (Région)", 'Ville', 'Photos'];
        $rows = [
            ['4200', 'true', 'Olympiades en équipe', 'Idée', '["Sportives"]', "Cohésion d'équipe\nMotiver", '1:30', '3:00', '45', 'Toute la France', '', '{"master":["x/master/1.jpg"]}'],
            ['4300', 'false', 'Atelier cuisine', 'Idée', '["Culinaires & Oenologiques"]', '', '2:00', '4:00', '0', '', 'Lyon', ''],
            ['256', 'true', 'Le Café de Paris', 'Hôtel', '', '', '', '', '', '', 'Biarritz', ''],
        ];
        $path = tempnam(sys_get_temp_dir(), 'mdm-legacy-act-');
        self::assertIsString($path);
        $this->csvFile = $path;
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }

    private function cleanTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
