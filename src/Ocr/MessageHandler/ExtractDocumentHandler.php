<?php

declare(strict_types=1);

namespace App\Ocr\MessageHandler;

use App\Ocr\Catalog\OcrFieldCatalog;
use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Entity\OcrSuggestion;
use App\Ocr\Enum\ExtractionStatus;
use App\Ocr\Message\AutoApplyOcrSuggestions;
use App\Ocr\Message\CleanupBoxFile;
use App\Ocr\Message\ExtractDocument;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\BoxProviderException;
use App\Ocr\Service\DocumentExtractionProviderInterface;
use App\Ocr\Service\OcrExtractionConsolidator;
use App\Ocr\Service\OcrRecoverableExtractionException;
use App\Ocr\Service\OcrValueAccessor;
use App\Ocr\Service\PdfDocumentProcessor;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class ExtractDocumentHandler
{
    public function __construct(
        private DocumentExtractionRepository $extractions,
        private DocumentExtractionProviderInterface $provider,
        private PrivateObjectStorageInterface $storage,
        private PdfDocumentProcessor $pdf,
        private OcrFieldCatalog $catalog,
        private OcrExtractionConsolidator $consolidator,
        private FicheImportSchemaRegistry $schemas,
        private OcrValueAccessor $accessor,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(ExtractDocument $message): void
    {
        $extraction = $this->extractions->find($message->extractionId);
        if (!$extraction instanceof DocumentExtraction || !in_array($extraction->status(), [ExtractionStatus::Queued, ExtractionStatus::Failed], true)) { return; }
        $temp = tempnam(sys_get_temp_dir(), 'mdm-ocr-source-');
        if (false === $temp) { throw new RecoverableMessageHandlingException('Impossible de créer le PDF temporaire.'); }
        $batches = [];
        $boxFilesToClean = [];
        try {
            // Copie en flux : un PDF de 50 Mo ne doit pas être chargé
            // entièrement en mémoire par le worker.
            $source = $this->storage->readStream($extraction->document()->originalStorageKey());
            try {
                $destination = fopen($temp, 'wb');
                if (false === $destination) { throw new \RuntimeException('Impossible de préparer le PDF DAM.'); }
                try { stream_copy_to_stream($source, $destination); } finally { fclose($destination); }
            } finally { fclose($source); }
            if (hash_file('sha256', $temp) !== $extraction->documentChecksum()) { throw new \DomainException('L’empreinte du document DAM ne correspond plus à l’extraction.'); }
            $pages = $this->pdf->inspect($temp);
            $extraction->start($pages);
            $batches = $this->pdf->batches($temp, $pages);
            $responses = [];
            foreach ($batches as $batch) {
                $name = sprintf('mdm-ocr-%s-p%d-%d.pdf', $extraction->id(), $batch->firstPage, $batch->lastPage);
                $fileId = $this->provider->upload($batch->path, $name);
                $extraction->rememberTemporaryBoxFile($fileId);
                try {
                    $responses[] = ['first_page' => $batch->firstPage, 'response' => $this->provider->extract($fileId, $this->catalog->boxFields($extraction->fiche()->type()))];
                } finally {
                    try { $this->provider->delete($fileId); $extraction->forgetTemporaryBoxFile($fileId); }
                    catch (\Throwable) {
                        $boxFilesToClean[] = $fileId;
                        $this->outbox->enqueue(new CleanupBoxFile($extraction->id(), $fileId));
                    }
                }
            }
            $result = $this->consolidator->consolidate($responses);
            $schema = $this->schemas->for($extraction->fiche()->type());
            $aggregate = $schema->findAggregateByFiche($extraction->fiche());
            if (null === $aggregate) { throw new \DomainException('L’agrégat PIM lié à la fiche est introuvable.'); }
            $definitions = [];
            foreach ($extraction->schemaSnapshot()['fields'] ?? [] as $field) { if (is_array($field) && is_string($field['path'] ?? null)) { $definitions[$field['path']] = $field; } }
            foreach ($result['suggestions'] as $suggestion) {
                $definition = $definitions[$suggestion['path']] ?? null;
                if (!is_array($definition)) { continue; }
                $this->entityManager->persist(new OcrSuggestion(
                    $extraction, $suggestion['path'], (string) ($definition['label'] ?? $suggestion['path']), (string) ($definition['type'] ?? 'string'),
                    $suggestion['value'], $this->accessor->read($extraction->fiche(), $aggregate, $suggestion['path']), $suggestion['confidence'], $suggestion['pages'],
                ));
            }
            $extraction->complete($result['raw'], $result['agent'], $result['model']);
            if ([] !== $result['suggestions']) {
                // L'application automatique (seuil paramétrable, 0 = manuel)
                // court dans son propre message : un refus de validation
                // métier ne doit pas faire échouer l'extraction.
                $this->outbox->enqueue(new AutoApplyOcrSuggestions($extraction->id()));
            }
        } catch (BoxProviderException $error) {
            if ($error->retryable) {
                // The worker transaction is rolled back before its failure event.
                // Carry failed deletions so the subscriber can durably schedule
                // cleanup outside that rolled-back extraction attempt.
                throw new OcrRecoverableExtractionException($error->getMessage(), $boxFilesToClean, $error, 1000 * ($error->retryAfter ?? 10));
            }
            $extraction->fail($error->getMessage());
        } catch (\DomainException $error) {
            $extraction->fail($error->getMessage());
        } finally {
            $this->pdf->cleanup($batches);
            if (is_file($temp)) { @unlink($temp); }
        }
    }
}
