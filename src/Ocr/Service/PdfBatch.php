<?php

declare(strict_types=1);

namespace App\Ocr\Service;

final readonly class PdfBatch
{
    public function __construct(public string $path, public int $firstPage, public int $lastPage)
    {
    }
}
