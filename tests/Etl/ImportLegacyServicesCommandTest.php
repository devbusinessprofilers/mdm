<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class ImportLegacyServicesCommandTest extends KernelTestCase
{
    private const TABLES = ['etl_legacy_photo', 'etl_legacy_fiche', 'pim_service_evenementiel', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message'];

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

    public function testImportCreatesServicesIdempotently(): void
    {
        $tester = $this->tester();
        $file = $this->writeSampleCsv();

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE type = 'service_evenementiel'"));
        self::assertSame(5100, (int) $this->connection->fetchOne('SELECT f.code FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 5100'));
        self::assertSame('publiee', $this->connection->fetchOne('SELECT f.status FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 5100'));
        self::assertSame('mobile', $this->connection->fetchOne('SELECT s.mode_intervention FROM pim_service_evenementiel s JOIN etl_legacy_fiche m ON m.fiche_id = s.id WHERE m.syspad_id = 5100'));
        self::assertSame('Prestataires de service', $this->connection->fetchOne('SELECT gamme FROM etl_legacy_fiche WHERE syspad_id = 5100'));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche_attribute_value WHERE attribute_code = 'TYPE_PRESTATAIRE'"));

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('déjà importées', $tester->getDisplay());
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE type = 'service_evenementiel'"));
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

        return new CommandTester($application->find('app:legacy:import-services'));
    }

    private function writeSampleCsv(): string
    {
        $headers = ['Id syspad', 'Publié / non publié', 'Nom Français', 'Gamme', 'Type de prestataire', 'Categorie', 'Description générale', 'Tarifs activité à partir de', "Rayon d'action (Région)", 'Ville', 'Photos'];
        $rows = [
            ['5100', 'true', 'Traiteur des Halles', 'Prestataires de service', 'Traiteurs', 'RSE', 'Traiteur événementiel.', '35', 'Île-de-France', '', '{"master":["x/master/1.jpg"]}'],
            ['5200', 'false', 'Prestataire mystère', 'Prestataires de service', '', '', '', '0', '', 'Lyon', ''],
            ['256', 'true', 'Le Café de Paris', 'Hôtel', '', '', '', '', '', 'Biarritz', ''],
        ];
        $path = tempnam(sys_get_temp_dir(), 'mdm-legacy-srv-');
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
