<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class ImportLegacyRestaurantsCommandTest extends KernelTestCase
{
    private const TABLES = ['etl_legacy_photo', 'etl_legacy_fiche', 'pim_restaurant_acces', 'pim_restaurant_periode_fermeture', 'pim_restaurant_salle', 'pim_restaurant', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message'];

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

    public function testImportCreatesRestaurantsIdempotently(): void
    {
        $tester = $this->tester();
        $file = $this->writeSampleCsv();

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE type = 'restaurant'"));
        self::assertSame(7100, (int) $this->connection->fetchOne('SELECT f.code FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 7100'));
        self::assertSame('publiee', $this->connection->fetchOne('SELECT f.status FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 7100'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_restaurant_salle'));
        self::assertSame('Restaurant', $this->connection->fetchOne('SELECT gamme FROM etl_legacy_fiche WHERE syspad_id = 7100'));

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('déjà importées', $tester->getDisplay());
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE type = 'restaurant'"));
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
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:legacy:import-restaurants'));
    }

    private function writeSampleCsv(): string
    {
        $headers = ['Id syspad', 'Publié / non publié', 'Nom Français', 'Gamme', 'Thématique', 'Description générale', 'Restauration / Gastronomie', 'Ville', 'Salle', 'Photos'];
        $salleJson = '{"9":{"Nom":"Salon privé","Capacité en Banquet":"30","Lumière du jour":"1","Accès PMR":"0","Dansant":"0"}}';
        $rows = [
            ['7100', 'true', 'La Table du Port', 'Restaurant', '["Gastronomique"]', 'Vue sur le port.', 'Cuisine de la mer.', 'Marseille', $salleJson, '{"master":["x/master/1.jpg"]}'],
            ['7200', 'false', 'Bistrot simple', 'Restaurant', '', '', 'Cuisine du marché.', 'Lyon', '', ''],
            ['256', 'true', 'Le Café de Paris', 'Hôtel', '', '', '', 'Biarritz', '', ''],
        ];
        $path = tempnam(sys_get_temp_dir(), 'mdm-legacy-rest-');
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
