<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class ImportLegacyTranslationsCommandTest extends KernelTestCase
{
    private const TABLES = ['enrichment_fiche_translation', 'pim_attribute_value_translation', 'etl_legacy_fiche', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche_search', 'pim_fiche', 'pim_localisation', 'outbox_message'];

    private Connection $connection;
    private ?string $dumpFile = null;

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
        if (null !== $this->dumpFile) {
            @unlink($this->dumpFile);
        }

        parent::tearDown();
    }

    public function testTranslationsAreImportedWithCoherenceRule(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // Lieu 8100 : desc identique au legacy → disponible ; chambres divergent → obsolète.
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel des Tests');
        $lieu->fiche()->assignImportedCode(8100);
        $lieu->changeDescGenerale('Description française.');
        $lieu->changeChambreDescGenerale('Nouveau texte chambres.');
        $lieu->changeAtout1('Vue océan');
        $lieu->changeLoisirInterne(['Surf']);
        $lieu->fiche()->publishForImport();
        $entityManager->persist($lieu);
        $entityManager->flush();
        $entityManager->clear();

        $tester = $this->tester();
        $tester->execute(['--file' => $this->writeDump()]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $rows = $this->connection->fetchAllAssociative('SELECT field_path, locale, status, origin, translated_text, suggested_text FROM enrichment_fiche_translation ORDER BY field_path, locale');
        self::assertCount(5, $rows, $tester->getDisplay());

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['field_path'].'|'.$row['locale']] = $row;
        }
        // Source identique → disponible, origin manual.
        self::assertSame('disponible', $byKey['lieu.descGenerale|en']['status']);
        self::assertSame('manual', $byKey['lieu.descGenerale|en']['origin']);
        self::assertSame('English description.', $byKey['lieu.descGenerale|en']['translated_text']);
        self::assertSame('disponible', $byKey['lieu.descGenerale|de']['status']);
        // Source divergente → obsolète, traduction conservée.
        self::assertSame('obsolete', $byKey['lieu.chambreDescGenerale|en']['status']);
        self::assertSame('Old rooms translation.', $byKey['lieu.chambreDescGenerale|en']['translated_text']);
        // Atouts et loisirs appariés par texte français (référentiels partagés).
        self::assertSame('Ocean view', $byKey['lieu.atout1|en']['translated_text']);
        self::assertSame('disponible', $byKey['lieu.atout1|en']['status']);
        self::assertSame('Surfing', $byKey['lieu.loisirInterne[0]|en']['translated_text']);
        // Libellé LOV apparié (« Mer » = TA_CADRE_ENV_1).
        self::assertSame('Sea', $this->connection->fetchOne(
            "SELECT t.translated_label FROM pim_attribute_value_translation t JOIN pim_attribute_value v ON v.id = t.value_id WHERE v.code = 'TA_CADRE_ENV_1' AND t.locale = 'en'",
        ));

        // Idempotence : seconde exécution sans doublon.
        $tester->execute(['--file' => $this->dumpFile]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(5, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM enrichment_fiche_translation'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_attribute_value_translation'));
        self::assertStringContainsString('déjà présentes', $tester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel dry-run');
        $lieu->fiche()->assignImportedCode(8100);
        $lieu->changeDescGenerale('Description française.');
        $entityManager->persist($lieu);
        $entityManager->flush();

        $tester = $this->tester();
        $tester->execute(['--file' => $this->writeDump(), '--dry-run' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM enrichment_fiche_translation'));
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:legacy:import-translations'));
    }

    private function writeDump(): string
    {
        $dump = <<<'SQL'
            CREATE TABLE `bp_lieu` (
              `id` int(11) NOT NULL,
              `produit_id` int(11) NOT NULL,
              `chambres_fr` longtext DEFAULT NULL,
              `salles_fr` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `bp_lieu` VALUES
            (55,710,'Ancien texte chambres.',NULL);
            CREATE TABLE `bp_produit` (
              `id` int(11) NOT NULL,
              `syspad_id` int(11) DEFAULT NULL,
              `description_fr` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `bp_produit` VALUES
            (710,8100,'Description française.'),
            (711,NULL,'Produit hors périmètre.');
            CREATE TABLE `i18n_translation_lieu` (
              `id` int(11) NOT NULL,
              `lieu_id` int(11) NOT NULL,
              `locale` varchar(8) NOT NULL,
              `field` varchar(32) NOT NULL,
              `content` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `i18n_translation_lieu` VALUES
            (1,55,'en','chambresFr','Old rooms translation.'),
            (2,55,'en','restaurationFr','Unmapped field.');
            CREATE TABLE `i18n_translation_produit` (
              `id` int(11) NOT NULL,
              `produit_id` int(11) NOT NULL,
              `locale` varchar(8) NOT NULL,
              `field` varchar(32) NOT NULL,
              `content` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `i18n_translation_produit` VALUES
            (10,710,'en','descriptionFr','English description.'),
            (11,710,'de','descriptionFr','Deutsche Beschreibung.'),
            (12,711,'en','descriptionFr','Orphan produit.');
            CREATE TABLE `bp_atout` (
              `id` int(11) NOT NULL,
              `nom` varchar(255) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `bp_atout` VALUES
            (1,'Vue océan');
            CREATE TABLE `i18n_translation_atout` (
              `id` int(11) NOT NULL,
              `atout_id` int(11) NOT NULL,
              `locale` varchar(8) NOT NULL,
              `field` varchar(32) NOT NULL,
              `content` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `i18n_translation_atout` VALUES
            (1,1,'en','nom','Ocean view');
            CREATE TABLE `bp_loisir` (
              `id` int(11) NOT NULL,
              `nom` varchar(255) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `bp_loisir` VALUES
            (1,'Surf');
            CREATE TABLE `i18n_translation_loisir` (
              `id` int(11) NOT NULL,
              `loisir_id` int(11) NOT NULL,
              `locale` varchar(8) NOT NULL,
              `field` varchar(32) NOT NULL,
              `content` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `i18n_translation_loisir` VALUES
            (1,1,'en','nom','Surfing');
            CREATE TABLE `bp_theme` (
              `id` int(11) NOT NULL,
              `nom` varchar(255) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `bp_theme` VALUES
            (1,'Mer');
            CREATE TABLE `i18n_translation_theme` (
              `id` int(11) NOT NULL,
              `theme_id` int(11) NOT NULL,
              `locale` varchar(8) NOT NULL,
              `field` varchar(32) NOT NULL,
              `content` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `i18n_translation_theme` VALUES
            (1,1,'en','nom','Sea');
            SQL;
        $path = tempnam(sys_get_temp_dir(), 'mdm-trad-dump-');
        self::assertIsString($path);
        $this->dumpFile = $path;
        file_put_contents($path, $dump);

        return $path;
    }

    private function cleanTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
