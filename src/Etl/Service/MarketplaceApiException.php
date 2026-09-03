<?php

declare(strict_types=1);

namespace App\Etl\Service;

use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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

    /**
     * L'exception à relancer depuis un handler Messenger : une panne
     * transitoire repart en relance, un refus permanent part directement en
     * failed (rejouer donnerait le même échec), où l'écouteur d'échec
     * l'enregistre.
     */
    public function pourMessenger(): \Throwable
    {
        return $this->retryable ? $this : new UnrecoverableMessageHandlingException($this->getMessage(), 0, $this);
    }
}
