<?php

declare(strict_types=1);

namespace App\Dam\Service;

final class DocumentUploadException extends \DomainException
{
    public function __construct(
        public readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }
}
