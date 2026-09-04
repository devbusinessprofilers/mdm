<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Enum\ImportJobStatus;
use App\Etl\Message\ProcessFicheImportBatch;
use App\Etl\Message\StartFicheImport;
use App\Etl\MessageHandler\ProcessFicheImportBatchHandler;
use App\Etl\MessageHandler\StartFicheImportHandler;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use App\Tests\Support\CommeUnWorker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Moteur de l'import (StartFicheImport puis ProcessFicheImportBatch) sur un
 * classeur au format d'export : création, mise à jour écrasante (cellule
 * vide = champ vidé, sites remis aux obligatoires), rapport d'erreurs et
 * garde d'idempotence à la redélivrance.
 */
#[Group('database')]
final class FicheImportProcessTest extends KernelTestCase
{
    private Connection $connection;
    private ?string $importFile = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->importFile && is_file($this->importFile)) {
            unlink($this->importFile);
        }
        if (isset($this->connection)) {
            foreach ([
                'etl_import_job_error', 'etl_import_job', 'pim_fiche_search', 'pim_fiche_attribute_value',
                'pim_fiche_site_diffusion', 'pim_site_diffusion',
                'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_ressource_lieu',
                'pim_fiche_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche',
                'pim_localisation', 'outbox_message', 'account_user',
            ] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }

        parent::tearDown();
    }

    public function testImportCreatesUpdatesAndReportsErrorsIdempotently(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get(Connection::class);
        foreach (['etl_import_job_error', 'etl_import_job', 'pim_salle', 'pim_fiche_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche_attribute_value', 'pim_fiche_site_diffusion', 'pim_site_diffusion', 'pim_fiche', 'pim_localisation', 'outbox_message'] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }

        // Référentiel des sites : un obligatoire (jamais listé dans le fichier,
        // réappliqué d'office) et deux sélectionnables par libellé ou code.
        $entityManager->persist(new SiteDiffusion('marketplace_bp', 'Business Profilers', 'Business Profilers', true, false, 0, []));
        $entityManager->persist(new SiteDiffusion('seminaire_paris', 'Séminaire PARIS', 'Sites thématiques', false, false, 1, []));
        $entityManager->persist(new SiteDiffusion('lyon', 'Lyon', 'Sites régionaux', false, false, 2, []));

        $existing = new Lieu();
        $existing->changeLabel('Lieu Existant');
        $entityManager->persist($existing);
        $entityManager->flush();
        $existingCode = $existing->fiche()->code();
        $existingId = $existing->fiche()->idString();

        /** @var string $projectDir */
        $projectDir = $container->getParameter('kernel.project_dir');
        $job = new FicheImportJob(TypeFiche::Lieu, 'import-test.xlsx', 'import-test@example.test');
        $this->importFile = $projectDir.'/var/import/'.$job->storagePath();
        if (!is_dir(dirname($this->importFile))) {
            mkdir(dirname($this->importFile), 0775, true);
        }

        $writer = new Writer();
        $writer->openToFile($this->importFile);
        $writer->getCurrentSheet()->setName('Lieux');
        $writer->addRow(Row::fromValues(['code', 'label', 'localisation_ville', 'generale_typologie', 'attribution_visibilite', 'salle_1_nom', 'salle_1_capacite_theatre']));
        // Sites résolus par libellé (accents, un par ligne) ou code, insensible à la casse.
        $writer->addRow(Row::fromValues(['', 'Nouveau Lieu Import', 'Paris', 'GENERALE_TYPOLOGIE_1', "Séminaire PARIS\nLYON", 'Salle Alpha', 120]));
        // Mise à jour écrasante : les cellules vides vident les champs, la
        // visibilité revient aux sites obligatoires seuls.
        $writer->addRow(Row::fromValues([$existingCode, 'Lieu Existant Modifié', '', '', '', '', '']));
        $writer->addRow(Row::fromValues(['', 'Lieu Erreur', '', 'CODE_INCONNU', '', '', '']));
        // Une feuille LOV ne doit jamais être importée.
        $writer->addNewSheetAndMakeItCurrent()->setName('LOV');
        $writer->addRow(Row::fromValues(['code', 'label']));
        $writer->addRow(Row::fromValues(['', 'Fiche fantôme de la notice']));
        $writer->close();

        $entityManager->persist($job);
        $entityManager->flush();
        $jobId = $job->idString();

        $container->get(StartFicheImportHandler::class)(new StartFicheImport($jobId));
        $entityManager->flush();

        $batchHandler = $container->get(ProcessFicheImportBatchHandler::class);
        CommeUnWorker::traiter($entityManager, $batchHandler, new ProcessFicheImportBatch($jobId, 2));

        $entityManager->clear();
        $job = $entityManager->find(FicheImportJob::class, Ulid::fromString($jobId));
        self::assertInstanceOf(FicheImportJob::class, $job);
        self::assertSame(ImportJobStatus::TermineAvecErreurs, $job->status());
        self::assertSame(3, $job->totalLines());
        self::assertSame(3, $job->processedLines());
        self::assertSame(1, $job->createdCount());
        self::assertSame(1, $job->updatedCount());
        self::assertSame(1, $job->errorCount());
        self::assertSame(4, $job->lastProcessedLine());

        $errors = $this->connection->fetchAllAssociative('SELECT line_number, column_name, message FROM etl_import_job_error ORDER BY id');
        self::assertCount(1, $errors);
        self::assertSame(4, (int) $errors[0]['line_number']);
        self::assertSame('generale_typologie', $errors[0]['column_name']);
        self::assertStringContainsString('« CODE_INCONNU »', (string) $errors[0]['message']);

        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
        $updatedLabel = $this->connection->fetchOne('SELECT label FROM pim_fiche WHERE id = ?', [Ulid::fromString($existingId)->toBinary()]);
        self::assertSame('Lieu Existant Modifié', $updatedLabel);
        self::assertSame('Salle Alpha', $this->connection->fetchOne('SELECT nom FROM pim_salle'));
        self::assertSame(120, (int) $this->connection->fetchOne('SELECT capacite_theatre FROM pim_salle'));
        // Attribution visibilité : les 2 sites listés + l'obligatoire réappliqué.
        $sitesRetenus = $this->connection->fetchFirstColumn(
            'SELECT s.code FROM pim_fiche_site_diffusion l
             INNER JOIN pim_site_diffusion s ON s.id = l.site_id
             INNER JOIN pim_fiche f ON f.id = l.fiche_id
             WHERE f.label = ? ORDER BY s.code',
            ['Nouveau Lieu Import'],
        );
        self::assertSame(['lyon', 'marketplace_bp', 'seminaire_paris'], $sitesRetenus);
        // Cellule vide sur la fiche mise à jour : le fichier fait foi, la
        // sélection revient aux sites obligatoires seuls.
        $sitesExistant = $this->connection->fetchFirstColumn(
            'SELECT s.code FROM pim_fiche_site_diffusion l
             INNER JOIN pim_site_diffusion s ON s.id = l.site_id
             WHERE l.fiche_id = ? ORDER BY s.code',
            [Ulid::fromString($existingId)->toBinary()],
        );
        self::assertSame(['marketplace_bp'], $sitesExistant);
        self::assertGreaterThan(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_message'));

        // Redélivrance du même batch : la garde lastProcessedLine évite tout retraitement.
        CommeUnWorker::traiter($entityManager, $batchHandler, new ProcessFicheImportBatch($jobId, 2));
        $entityManager->clear();
        $job = $entityManager->find(FicheImportJob::class, Ulid::fromString($jobId));
        self::assertInstanceOf(FicheImportJob::class, $job);
        self::assertSame(1, $job->createdCount());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }
}
