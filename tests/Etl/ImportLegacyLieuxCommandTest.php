<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\LegacyFicheMapping;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class ImportLegacyLieuxCommandTest extends KernelTestCase
{
    private const TABLES = ['etl_legacy_photo', 'etl_legacy_fiche', 'pim_salle', 'pim_acces_lieu', 'pim_ressource_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_periode_fermeture', 'pim_lieu', 'pim_fiche', 'pim_localisation', 'outbox_message'];

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

    public function testImportCreatesFichesIdempotently(): void
    {
        $tester = $this->tester();
        $file = $this->writeSampleCsv();

        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM etl_legacy_fiche'));
        self::assertSame('publiee', $this->connection->fetchOne("SELECT f.status FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 256"));
        // Le code fiche reprend l'Id syspad.
        self::assertSame(256, (int) $this->connection->fetchOne("SELECT f.code FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 256"));
        self::assertSame(300, (int) $this->connection->fetchOne("SELECT f.code FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 300"));
        self::assertSame('en_cours', $this->connection->fetchOne("SELECT f.status FROM pim_fiche f JOIN etl_legacy_fiche m ON m.fiche_id = f.id WHERE m.syspad_id = 300"));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_salle'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_search'));
        self::assertNotNull($this->connection->fetchOne('SELECT photos_json FROM etl_legacy_fiche WHERE syspad_id = 256'));
        // La description multiligne est conservée.
        $desc = (string) $this->connection->fetchOne('SELECT desc_generale FROM pim_lieu l JOIN etl_legacy_fiche m ON m.fiche_id = l.id WHERE m.syspad_id = 256');
        self::assertStringContainsString("ligne 1\nligne 2", $desc);

        // Seconde exécution : idempotente.
        $tester->execute(['--file' => $file]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('déjà importées', $tester->getDisplay());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }

    public function testDryRunWritesNothing(): void
    {
        $tester = $this->tester();
        $tester->execute(['--file' => $this->writeSampleCsv(), '--dry-run' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM etl_legacy_fiche'));
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:legacy:import-lieux'));
    }

    /**
     * CSV réduit : le lecteur s'appuie sur les en-têtes du fichier, les
     * colonnes absentes valent chaîne vide côté mapper.
     */
    private function writeSampleCsv(): string
    {
        $headers = ['Id syspad', 'Publié / non publié', 'Nom Français', 'Gamme', 'Classification', 'Thématique', 'Nombre de chambres', 'Pays', 'Ville', 'Salle', 'Description générale', 'Photos'];
        $rows = [
            ['256', 'true', 'Le Café de Paris', 'Hôtel', '★★★★', '["Mer"]', '19', 'France', 'Biarritz', '', "ligne 1\nligne 2", '{"master":["x/master/1.jpg"],"chambre":["x/chambre/1.jpg"]}'],
            ['999', 'true', 'Bistrot exclu', 'Restaurant', '', '', '', 'France', 'Paris', '', '', ''],
            ['300', 'false', 'Domaine du Lac', 'Lieu', '', '["Lac","Pas de Thème"]', '', 'France', 'Annecy', '{"1":{"Nom":"Grande salle","Superficie Salle en m2":"200","Capacité en Théâtre / Conférence":"150","Lumière du jour":"1","Accès PMR":"0","Dansant":"0"}}', 'Au bord du lac.', ''],
        ];
        $path = tempnam(sys_get_temp_dir(), 'mdm-legacy-csv-');
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
