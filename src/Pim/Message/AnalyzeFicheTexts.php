<?php

declare(strict_types=1);

namespace App\Pim\Message;

final readonly class AnalyzeFicheTexts
{
    public function __construct(public string $ficheId) {}
}
