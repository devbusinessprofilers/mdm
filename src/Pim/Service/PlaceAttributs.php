<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Attributs d'un lieu tels que renvoyés par Geoapify Place Details (tags
 * OpenStreetMap normalisés). Un booléen à null signifie « non renseigné dans
 * OSM » — on ne propose alors rien.
 */
final readonly class PlaceAttributs
{
    /**
     * @param list<string> $cuisines valeurs OSM `cuisine` (minuscules)
     * @param list<string> $regimes  régimes alimentaires OSM actifs (vegan, vegetarian, halal, kosher, organic)
     */
    public function __construct(
        public array $cuisines = [],
        public array $regimes = [],
        public ?bool $accesPmr = null,
        public ?bool $toilettesPmr = null,
        public ?bool $terrasse = null,
        public ?bool $climatisation = null,
        public ?bool $wifi = null,
        public ?string $siteWeb = null,
    ) {}

    public function estVide(): bool
    {
        return [] === $this->cuisines
            && [] === $this->regimes
            && null === $this->accesPmr
            && null === $this->toilettesPmr
            && null === $this->terrasse
            && null === $this->climatisation
            && null === $this->wifi
            && null === $this->siteWeb;
    }
}
