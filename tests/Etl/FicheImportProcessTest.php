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
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

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
                'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_ressource_lieu',
                'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche',
                'pim_localisation', 'outbox_message', 'account_user',
            ] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }

        parent::tearDown();
    }

    public function testCsvImportCreatesUpdatesAndReportsErrorsIdempotently(): void
    {
        $this->runImportScenario('csv', function (string $path, int $existingCode): void {
            file_put_contents($path, implode("\n", [
                'code;label;localisation_ville;generale_typologie;salle_1_nom;salle_1_capacite_theatre',
                ';Nouveau Lieu Import;Paris;GENERALE_TYPOLOGIE_1;Salle Alpha;120',
                $existingCode.';Lieu Existant Modifié;;;;',
                ';Lieu Erreur;;CODE_INCONNU;;',
            ]));
        }, 'Salle Alpha');
    }

    public function testXlsxImportReadsTheFirstSheetWithNormalizedCells(): void
    {
        $this->runImportScenario('xlsx', function (string $path, int $existingCode): void {
            $writer = new Writer();
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Données');
            $writer->addRow(Row::fromValues(['code', 'label', 'localisation_ville', 'generale_typologie', 'salle_1_nom', 'salle_1_capacite_theatre']));
            $writer->addRow(Row::fromValues(['', 'Nouveau Lieu Import', 'Paris', 'GENERALE_TYPOLOGIE_1', 'Salle Alpha', 120]));
            $writer->addRow(Row::fromValues([$existingCode, 'Lieu Existant Modifié', '', '', '', '']));
            $writer->addRow(Row::fromValues(['', 'Lieu Erreur', '', 'CODE_INCONNU', '', '']));
            // Une seconde feuille de notice ne doit jamais être importée.
            $writer->addNewSheetAndMakeItCurrent()->setName('Notice & LOV');
            $writer->addRow(Row::fromValues(['code', 'label']));
            $writer->addRow(Row::fromValues(['', 'Fiche fantôme de la notice']));
            $writer->close();
        }, 'Salle Alpha');
    }

    private function runImportScenario(string $extension, callable $writeFixture, string $expectedSalle): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get(Connection::class);
        foreach (['etl_import_job_error', 'etl_import_job', 'pim_salle', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message'] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }

        $existing = new Lieu();
        $existing->changeLabel('Lieu Existant');
        $entityManager->persist($existing);
        $entityManager->flush();
        $existingCode = $existing->fiche()->code();
        $existingId = $existing->fiche()->idString();

        /** @var string $projectDir */
        $projectDir = $container->getParameter('kernel.project_dir');
        $job = new FicheImportJob(TypeFiche::Lieu, 'import-test.'.$extension, 'import-test@example.test', $extension);
        $this->importFile = $projectDir.'/var/import/'.$job->storagePath();
        if (!is_dir(dirname($this->importFile))) {
            mkdir(dirname($this->importFile), 0775, true);
        }
        $writeFixture($this->importFile, $existingCode);
        $entityManager->persist($job);
        $entityManager->flush();
        $jobId = $job->idString();

        $container->get(StartFicheImportHandler::class)(new StartFicheImport($jobId));
        $entityManager->flush();

        $batchHandler = $container->get(ProcessFicheImportBatchHandler::class);
        $batchHandler(new ProcessFicheImportBatch($jobId, 2));

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

        $errors = $this->connection->fetchAllAssociative('SELECT line_number, column_name FROM etl_import_job_error ORDER BY id');
        self::assertCount(1, $errors);
        self::assertSame(4, (int) $errors[0]['line_number']);
        self::assertSame('generale_typologie', $errors[0]['column_name']);

        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
        $updatedLabel = $this->connection->fetchOne('SELECT label FROM pim_fiche WHERE id = ?', [Ulid::fromString($existingId)->toBinary()]);
        self::assertSame('Lieu Existant Modifié', $updatedLabel);
        self::assertSame($expectedSalle, $this->connection->fetchOne('SELECT nom FROM pim_salle'));
        self::assertSame(120, (int) $this->connection->fetchOne('SELECT capacite_theatre FROM pim_salle'));
        self::assertGreaterThan(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_message'));

        // Redélivrance du même batch : la garde lastProcessedLine évite tout retraitement.
        $batchHandler(new ProcessFicheImportBatch($jobId, 2));
        $entityManager->clear();
        $job = $entityManager->find(FicheImportJob::class, Ulid::fromString($jobId));
        self::assertInstanceOf(FicheImportJob::class, $job);
        self::assertSame(1, $job->createdCount());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }
}
