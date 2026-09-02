<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\CritereGeo;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Repository\SiteDiffusionRepository;

/**
 * Attribution automatique de la visibilité géographique (CDC §10.1) : une
 * fiche est rattachée aux sites de diffusion dont l'un des critères couvre
 * son adresse — ville + rayon (à vol d'oiseau), département, région ou pays.
 * Ajout seul, jamais de retrait : passer dans la zone d'un site ne retire
 * aucun canal choisi par ailleurs, et l'ajout est une mise à jour technique
 * sans transition de workflow.
 */
final readonly class SiteDiffusionGeoAttribueur
{
    /** Rayon terrestre moyen (km), celui du haversine SQL de GrandeVilleReferenceRepository. */
    private const RAYON_TERRE_KM = 6371.0;

    public function __construct(private SiteDiffusionRepository $sites)
    {
    }

    /** @return list<SiteDiffusion> Sites actifs dont au moins un critère couvre l'adresse de la fiche. */
    public function sitesPour(Fiche $fiche): array
    {
        $localisation = $fiche->localisation();
        if (null === $localisation) {
            return [];
        }

        return array_values(array_filter(
            $this->sites->findActifsOrdonnes(),
            static fn (SiteDiffusion $site): bool => self::matche($site, $localisation),
        ));
    }

    /** @return int Nombre de sites réellement ajoutés (fiche déjà couverte = 0). */
    public function attribuer(Fiche $fiche): int
    {
        $sites = $this->sitesPour($fiche);

        return [] === $sites ? 0 : $fiche->ajouterSitesDiffusion($sites);
    }

    public static function matche(SiteDiffusion $site, Localisation $localisation): bool
    {
        foreach ($site->criteresGeo() as $critere) {
            if (self::critereSatisfait($critere, $localisation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un critère ville exige des coordonnées sur la fiche : sans GPS, il
     * n'est pas satisfait (la fiche reste éligible via département/région/
     * pays). Les critères département et région ne visent que la France —
     * « Berlin » est aussi un département allemand dans les données.
     */
    private static function critereSatisfait(CritereGeo $critere, Localisation $localisation): bool
    {
        return match ($critere->type) {
            CritereGeo::TYPE_PAYS => $critere->countryCode === $localisation->countryCode(),
            CritereGeo::TYPE_REGION => 'FR' === $localisation->countryCode()
                && null !== $localisation->region()
                && ReferentielGeographiqueFrancais::cle((string) $critere->region) === ReferentielGeographiqueFrancais::cle($localisation->region()),
            CritereGeo::TYPE_DEPARTEMENT => 'FR' === $localisation->countryCode()
                && null !== $localisation->departement()
                && ReferentielGeographiqueFrancais::cle((string) $critere->departement) === ReferentielGeographiqueFrancais::cle($localisation->departement()),
            CritereGeo::TYPE_VILLE => null !== $localisation->latitude()
                && null !== $localisation->longitude()
                && self::distanceKm(
                    (float) $critere->latitude,
                    (float) $critere->longitude,
                    (float) $localisation->latitude(),
                    (float) $localisation->longitude(),
                ) <= (float) $critere->rayonKm,
            default => false,
        };
    }

    /** Distance à vol d'oiseau (haversine) entre deux points, en kilomètres. */
    public static function distanceKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $deltaLatitude = deg2rad($latitudeB - $latitudeA);
        $deltaLongitude = deg2rad($longitudeB - $longitudeA);
        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($deltaLongitude / 2) ** 2;

        return 2 * self::RAYON_TERRE_KM * asin(min(1.0, sqrt($a)));
    }
}
