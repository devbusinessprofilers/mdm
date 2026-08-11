<?php

declare(strict_types=1);

namespace App\Etl\Service;

final class MarketplaceApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly bool $retryable = true,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Vrai pour les pannes transitoires (5xx, réseau) que Messenger peut
     * relancer ; faux pour les refus permanents (4xx : payload invalide,
     * droits manquants) où toute relance rejouerait le même échec.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
