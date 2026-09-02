<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Service\ReferentielGeographiqueFrancais;
use Symfony\Component\Intl\Countries;

/**
 * Critère géographique d'un site de diffusion : la zone que le site
 * représente. Quatre formes — ville avec rayon (coordonnées issues du
 * géocodage de la ville, jamais saisies), département, région, pays. Un site
 * en porte une liste (OU logique) sérialisée en JSON ; une fiche localisée
 * dans l'une des zones est rattachée automatiquement au site.
 */
final readonly class CritereGeo
{
    public const TYPE_VILLE = 'ville';
    public const TYPE_DEPARTEMENT = 'departement';
    public const TYPE_REGION = 'region';
    public const TYPE_PAYS = 'pays';

    private function __construct(
        public string $type,
        public ?string $ville,
        public ?string $latitude,
        public ?string $longitude,
        public ?int $rayonKm,
        public ?string $departement,
        public ?string $region,
        public ?string $countryCode,
    ) {
    }

    public static function ville(string $ville, string $latitude, string $longitude, int $rayonKm): self
    {
        $ville = trim($ville);
        $latitude = Localisation::normalizeLatitude($latitude);
        $longitude = Localisation::normalizeLongitude($longitude);
        if ('' === $ville || null === $latitude || null === $longitude) {
            throw new \InvalidArgumentException('Un critère ville exige un libellé et des coordonnées géocodées.');
        }
        if ($rayonKm < 1) {
            throw new \InvalidArgumentException('Le rayon doit être d\'au moins un kilomètre.');
        }

        return new self(self::TYPE_VILLE, $ville, $latitude, $longitude, $rayonKm, null, null, null);
    }

    public static function departement(string $departement): self
    {
        $departement = trim($departement);
        if ('' === $departement) {
            throw new \InvalidArgumentException('Un critère département exige un libellé.');
        }

        return new self(self::TYPE_DEPARTEMENT, null, null, null, null, ReferentielGeographiqueFrancais::normaliserDepartement($departement), null, null);
    }

    public static function region(string $region): self
    {
        $region = trim($region);
        if ('' === $region) {
            throw new \InvalidArgumentException('Un critère région exige un libellé.');
        }

        return new self(self::TYPE_REGION, null, null, null, null, null, ReferentielGeographiqueFrancais::normaliserRegion($region), null);
    }

    public static function pays(string $countryCode): self
    {
        $countryCode = strtoupper(trim($countryCode));
        if (1 !== preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new \InvalidArgumentException('Un critère pays exige un code ISO à deux lettres.');
        }

        return new self(self::TYPE_PAYS, null, null, null, null, null, null, $countryCode);
    }

    /**
     * Relit un critère sérialisé, avec tolérance : une entrée corrompue ou
     * d'un type inconnu vaut null plutôt qu'une exception — le référentiel
     * des sites doit rester lisible même si une ligne JSON a vieilli.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $texte = static fn (string $cle): ?string => is_string($data[$cle] ?? null) && '' !== trim($data[$cle]) ? trim($data[$cle]) : null;
        $ville = $texte('ville');
        $latitude = $texte('latitude');
        $longitude = $texte('longitude');
        $departement = $texte('departement');
        $region = $texte('region');
        $countryCode = $texte('countryCode');
        $rayonKm = is_int($data['rayonKm'] ?? null) ? $data['rayonKm'] : null;
        try {
            return match ($data['type'] ?? null) {
                self::TYPE_VILLE => null === $ville || null === $latitude || null === $longitude || null === $rayonKm
                    ? null
                    : self::ville($ville, $latitude, $longitude, $rayonKm),
                self::TYPE_DEPARTEMENT => null === $departement ? null : self::departement($departement),
                self::TYPE_REGION => null === $region ? null : self::region($region),
                self::TYPE_PAYS => null === $countryCode ? null : self::pays($countryCode),
                default => null,
            };
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<string, string|int> Forme compacte persistée (clés nulles omises). */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'ville' => $this->ville,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rayonKm' => $this->rayonKm,
            'departement' => $this->departement,
            'region' => $this->region,
            'countryCode' => $this->countryCode,
        ], static fn (string|int|null $valeur): bool => null !== $valeur);
    }

    /** Résumé humain pour les écrans d'administration (« Tours + 10 km »). */
    public function resume(): string
    {
        return match ($this->type) {
            self::TYPE_VILLE => sprintf('%s + %d km', $this->ville, $this->rayonKm),
            self::TYPE_DEPARTEMENT => sprintf('Département %s', $this->departement),
            self::TYPE_REGION => sprintf('Région %s', $this->region),
            self::TYPE_PAYS => sprintf('Pays %s', Countries::exists((string) $this->countryCode) ? Countries::getName((string) $this->countryCode, 'fr') : $this->countryCode),
            default => $this->type,
        };
    }
}
