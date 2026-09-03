<?php

declare(strict_types=1);

namespace App\Vision\Service;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class OpenAiProviderException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly ?int $retryAfter = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** La relance Messenger d'une panne transitoire, au délai demandé par le fournisseur (sinon $delaiParDefautSecondes). */
    public function relance(int $delaiParDefautSecondes): RecoverableMessageHandlingException
    {
        return new RecoverableMessageHandlingException($this->getMessage(), previous: $this, retryDelay: 1000 * ($this->retryAfter ?? $delaiParDefautSecondes));
    }
}
