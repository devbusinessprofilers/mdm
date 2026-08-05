<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class OcrRecoverableExtractionException extends RecoverableMessageHandlingException
{
    /** @param list<string> $boxFilesToClean */
    public function __construct(string $message, public readonly array $boxFilesToClean, ?\Throwable $previous = null, ?int $retryDelay = null)
    {
        parent::__construct($message, previous: $previous, retryDelay: $retryDelay);
    }
}
