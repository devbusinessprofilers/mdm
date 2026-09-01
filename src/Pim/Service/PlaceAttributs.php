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
        /** Enseigne OSM (`brand`), casse d'origine conservée. */
        public ?string $marque = null,
        /** Classement OSM `stars`, borné 1..5 (null hors bornes ou absent). */
        public ?int $etoiles = null,
        /** Piscines : seul `swimming_pool=indoor|outdoor` est décidable — un simple « yes » reste null. */
        public ?bool $piscineInterieure = null,
        public ?bool $piscineExterieure = null,
        public ?bool $sauna = null,
        public ?bool $spa = null,
        public ?bool $jardin = null,
        public ?bool $parking = null,
        public ?bool $ascenseur = null,
        /** Nombre de chambres OSM (`rooms`), borné 1..2000 (null hors bornes ou absent). */
        public ?int $chambres = null,
        /** Tag OSM `opening_hours` brut — à traduire par HorairesOsm::parser(). */
        public ?string $horairesOuverture = null,
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
            && null === $this->siteWeb
            && null === $this->marque
            && null === $this->etoiles
            && null === $this->piscineInterieure
            && null === $this->piscineExterieure
            && null === $this->sauna
            && null === $this->spa
            && null === $this->jardin
            && null === $this->parking
            && null === $this->ascenseur
            && null === $this->chambres
            && null === $this->horairesOuverture;
    }
}
