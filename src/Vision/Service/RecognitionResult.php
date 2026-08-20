<?php

declare(strict_types=1);

namespace App\Vision\Service;

final readonly class RecognitionResult
{
    /**
     * @param list<string>          $keywords
     * @param array<string, string> $extras   informations de contexte (type de vue, intérieur/extérieur…)
     * @param array<string, mixed>  $raw      métadonnées du fournisseur
     */
    public function __construct(
        public string $legende,
        public array $keywords,
        public array $extras,
        public array $raw,
    ) {
    }
}
