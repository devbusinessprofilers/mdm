<?php

declare(strict_types=1);

namespace App\Ocr\MessageHandler;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Message\CleanupBoxFile;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\BoxProviderException;
use App\Ocr\Service\DocumentExtractionProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class CleanupBoxFileHandler
{
    public function __construct(private DocumentExtractionProviderInterface $provider, private DocumentExtractionRepository $extractions) {}

    public function __invoke(CleanupBoxFile $message): void
    {
        $extraction = $this->extractions->find($message->extractionId);
        if (!$extraction instanceof DocumentExtraction || !in_array($message->fileId, $extraction->temporaryBoxFileIds(), true)) { return; }
        try { $this->provider->delete($message->fileId); $extraction->forgetTemporaryBoxFile($message->fileId); }
        catch (BoxProviderException $error) {
            if ($error->retryable) { throw new RecoverableMessageHandlingException($error->getMessage(), previous: $error, retryDelay: 1000 * ($error->retryAfter ?? 60)); }
            throw $error;
        }
    }
}
