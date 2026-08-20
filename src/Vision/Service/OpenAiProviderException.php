<?php

declare(strict_types=1);

namespace App\Vision\Service;

final class OpenAiProviderException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly ?int $retryAfter = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
