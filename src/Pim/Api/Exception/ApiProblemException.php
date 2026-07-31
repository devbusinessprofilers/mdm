<?php

declare(strict_types=1);

namespace App\Pim\Api\Exception;

final class ApiProblemException extends \RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $type,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
