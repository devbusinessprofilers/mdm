<?php

declare(strict_types=1);

namespace App\Ocr\MessageHandler;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Message\CleanupBoxFile;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\BoxProviderException;
use App\Ocr\Service\DocumentExtractionProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CleanupBoxFileHandler
{
    public function __construct(private DocumentExtractionProviderInterface $provider, private DocumentExtractionRepository $extractions)
    {
    }

    public function __invoke(CleanupBoxFile $message): void
    {
        $extraction = $this->extractions->find($message->extractionId);
        if (!$extraction instanceof DocumentExtraction || !in_array($message->fileId, $extraction->temporaryBoxFileIds(), true)) {
            return;
        }
        try {
            $this->provider->delete($message->fileId);
            $extraction->forgetTemporaryBoxFile($message->fileId);
        } catch (BoxProviderException $error) {
            if ($error->retryable) {
                throw $error->relance(60);
            }
            throw $error;
        }
    }
}
