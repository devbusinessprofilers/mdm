<?php

declare(strict_types=1);

namespace App\Ocr\Service;

final class OcrReviewException extends \DomainException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
