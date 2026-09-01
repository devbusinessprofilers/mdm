<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Repository\AeroportReferenceRepository;
use App\Pim\Repository\GrandeVilleReferenceRepository;
use Psr\Log\LoggerInterface;

/**
 * Suggestions du bloc Accès d'une fiche : ce qu'il y a autour de ses
 * coordonnées GPS, au plus une proposition par type. Aéroport et grande
 * ville sortent des référentiels statiques locaux (OurAirports, GeoNames) ;
 * gare, métro et tramway de Geoapify Places (OSM) — clé absente ou API en
 * panne, ces trois types manquent simplement, le reste est servi. Les
 * distances et durées (gamme Lieu) passent par Geoapify Routing, à pied en
 * périmètre piéton et en voiture au-delà, avec repli sur le vol d'oiseau
 * sans durée quand l'itinéraire échoue. Sans distances (Restaurant, qui n'a
 * que type + nom), le vol d'oiseau est glissé dans le nom.
 */
final class AccesSuggesteur
{
    private const RAYON_AEROPORT_KM = 300.0;
    private const RAYON_GRANDE_VILLE_KM = 200.0;
    private const POPULATION_MIN = 100_000;
    /** En deçà, la fiche est DANS la grande ville : rien à suggérer. */
    private const VILLE_TROP_PROCHE_KM = 3.0;
    private const RAYON_POI_METRES = 10_000;
    /** Une station de métro ou de tram n'est un accès qu'à portée de marche. */
    private const RAYON_STATION_METRES = 2_500;
    /** Au-delà de ce vol d'oiseau, l'itinéraire se calcule en voiture. */
    private const SEUIL_MARCHE_KM = 1.5;

    private const CATEGORIES = [
        'gare' => 'public_transport.train',
        'metro' => 'public_transport.subway',
        'tramway' => 'public_transport.tram',
    ];

    public function __construct(
        private readonly AeroportReferenceRepository $aeroports,
        private readonly GrandeVilleReferenceRepository $grandesVilles,
        private readonly GeoapifyClient $geoapify,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $typesExclus types (valeurs TypeAccesLieu) déjà présents dans le formulaire
     *
     * @return list<AccesSuggere>
     *
     * @throws \DomainException quand la fiche n'a pas de coordonnées GPS
     */
    public function suggerer(Fiche $fiche, array $typesExclus, bool $avecDistances): array
    {
        $latitude = $fiche->localisation()?->latitude();
        $longitude = $fiche->localisation()?->longitude();
        if (null === $latitude || null === $longitude) {
            throw new \DomainException('La fiche n\'a pas de coordonnées GPS — renseignez et vérifiez d\'abord son adresse.');
        }
        $lat = (float) $latitude;
        $lon = (float) $longitude;
        $manque = static fn (TypeAccesLieu $type): bool => !in_array($type->value, $typesExclus, true);

        $suggestions = [];
        if ($manque(TypeAccesLieu::Aeroport) && null !== ($aeroport = $this->aeroports->plusProche($lat, $lon, self::RAYON_AEROPORT_KM))) {
            $nom = null === $aeroport['codeIata'] ? $aeroport['nom'] : sprintf('%s (%s)', $aeroport['nom'], $aeroport['codeIata']);
            $suggestions[] = $this->entree(TypeAccesLieu::Aeroport, $nom, $latitude, $longitude, $aeroport['latitude'], $aeroport['longitude'], $aeroport['distanceKm'], $avecDistances);
        }
        foreach ($this->transportsProches($latitude, $longitude, $typesExclus) as $type => $poi) {
            $suggestions[] = $this->entree(TypeAccesLieu::from($type), $poi['nom'], $latitude, $longitude, (float) $poi['latitude'], (float) $poi['longitude'], (float) ($poi['distanceMetres'] ?? 0) / 1000.0, $avecDistances);
        }
        if ($manque(TypeAccesLieu::GrandeVille) && null !== ($ville = $this->grandesVilles->plusProche($lat, $lon, self::RAYON_GRANDE_VILLE_KM, self::POPULATION_MIN))
            && $ville['distanceKm'] >= self::VILLE_TROP_PROCHE_KM) {
            $suggestions[] = $this->entree(TypeAccesLieu::GrandeVille, $ville['nom'], $latitude, $longitude, $ville['latitude'], $ville['longitude'], $ville['distanceKm'], $avecDistances);
        }
        // Ordre de lecture du bloc : du plus lointain cadre (aéroport) au plus
        // local, aligné sur l'ordre de l'enum.
        $ordre = array_flip(array_map(static fn (TypeAccesLieu $type): string => $type->value, TypeAccesLieu::cases()));
        usort($suggestions, static fn (AccesSuggere $a, AccesSuggere $b): int => ($ordre[$a->type] ?? 99) <=> ($ordre[$b->type] ?? 99));

        return $suggestions;
    }

    /**
     * Gare, métro et tramway manquants, en un seul appel Places : la station
     * nommée la plus proche de chaque catégorie, bornée à portée de marche
     * pour métro et tram. API indisponible = types absents, pas d'échec.
     *
     * @param list<string> $typesExclus
     *
     * @return array<string, array{nom: string, latitude: string, longitude: string, distanceMetres: ?int, categories: list<string>}>
     */
    private function transportsProches(string $latitude, string $longitude, array $typesExclus): array
    {
        $categories = array_diff_key(self::CATEGORIES, array_flip($typesExclus));
        if ([] === $categories || !$this->geoapify->isConfigured()) {
            return [];
        }
        try {
            $pois = $this->geoapify->poisProches($latitude, $longitude, array_values($categories), self::RAYON_POI_METRES, 40);
        } catch (EnrichissementIndisponibleException $exception) {
            $this->logger->warning('Geoapify Places indisponible pour la suggestion d\'accès.', ['exception' => $exception]);

            return [];
        }
        $retenus = [];
        foreach ($pois as $poi) {
            foreach ($categories as $type => $categorie) {
                if (isset($retenus[$type]) || !in_array($categorie, $poi['categories'], true)) {
                    continue;
                }
                if ('gare' !== $type && ($poi['distanceMetres'] ?? 0) > self::RAYON_STATION_METRES) {
                    continue;
                }
                // Constaté sur données réelles : des stations de métro portent
                // AUSSI la catégorie train — une gare est un train sans subway.
                if ('gare' === $type && in_array(self::CATEGORIES['metro'], $poi['categories'], true)) {
                    continue;
                }
                $retenus[$type] = $poi;
            }
        }

        return $retenus;
    }

    /**
     * Une entrée finalisée : itinéraire réel quand la gamme porte des
     * distances, vol d'oiseau dans le nom sinon.
     */
    private function entree(TypeAccesLieu $type, string $nom, string $deLatitude, string $deLongitude, float $versLatitude, float $versLongitude, float $volOiseauKm, bool $avecDistances): AccesSuggere
    {
        $volOiseauKm = $volOiseauKm > 0.0 ? $volOiseauKm : self::haversineKm((float) $deLatitude, (float) $deLongitude, $versLatitude, $versLongitude);
        if (!$avecDistances) {
            // Virgule décimale : ce nom part tel quel dans un champ texte.
            return new AccesSuggere($type->value, sprintf('%s (%s km)', $nom, str_replace('.', ',', self::kmLisible($volOiseauKm))));
        }
        $aPied = in_array($type, [TypeAccesLieu::Metro, TypeAccesLieu::Tramway], true) || $volOiseauKm <= self::SEUIL_MARCHE_KM;
        $itineraire = null;
        try {
            $itineraire = $this->geoapify->itineraire($deLatitude, $deLongitude, (string) $versLatitude, (string) $versLongitude, $aPied ? 'walk' : 'drive');
        } catch (EnrichissementIndisponibleException $exception) {
            $this->logger->warning('Geoapify Routing indisponible pour la suggestion d\'accès.', ['exception' => $exception]);
        }

        return new AccesSuggere(
            $type->value,
            $nom,
            null === $itineraire ? self::kmLisible($volOiseauKm) : self::kmLisible($itineraire['distanceMetres'] / 1000.0),
            null === $itineraire ? null : max(1, (int) round($itineraire['dureeSecondes'] / 60.0)),
            $aPied ? 'À pied' : 'Voiture',
        );
    }

    /** Kilomètres en notation canonique : une décimale, entier à partir de 10 km. */
    private static function kmLisible(float $km): string
    {
        return $km >= 10.0 ? (string) (int) round($km) : number_format(max($km, 0.1), 1, '.', '');
    }

    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371.0 * 2 * asin(min(1.0, sqrt($a)));
    }
}
