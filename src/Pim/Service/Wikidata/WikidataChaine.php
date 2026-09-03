<?php

declare(strict_types=1);

namespace App\Pim\Service\Wikidata;

/** Chaîne ou marque hôtelière : nom canonique et libellés alternatifs (alias). */
final readonly class WikidataChaine
{
    /** @param list<string> $alias */
    public function __construct(
        public string $nom,
        public array $alias = [],
    ) {
    }
}
