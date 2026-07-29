<?php

declare(strict_types=1);

namespace App\Pim\Message;

final readonly class IndexFiche
{
    public function __construct(public string $ficheId)
    {
    }
}
