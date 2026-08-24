<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client Geoapify (géocodage mondial sur données OpenStreetMap) pour les
 * adresses hors de France. Une adresse passe par l'endpoint simple ; un lot
 * passe par l'API batch asynchrone (~moitié du coût en crédits) : un job par
 * pays — le filtre countrycode borne la recherche et écarte les homonymes —
 * soumis puis interrogé jusqu'au résultat. Clé absente = client désactivé.
 *
 * Attribution requise par le plan gratuit : « © Geoapify — données
 * © OpenStreetMap contributors » sur les écrans qui affichent ces données.
 */
final class GeoapifyClient implements GeocodeurEtrangerInterface
{
    /** Un job batch accepte 1 000 adresses au plus (plafond Geoapify). */
    private const TAILLE_JOB = 1000;
    private const ESSAIS_POLLING = 60;

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $endpoint,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly int $intervallePolling = 5,
    ) {
        $this->httpClient = $httpClient->withOptions([
            'timeout' => 30,
            'max_duration' => 120,
        ]);
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey) && '' !== trim($this->endpoint);
    }

    /**
     * Attributs OpenStreetMap du lieu situé aux coordonnées données (Place
     * Details). Retourne null si le lieu est introuvable ou l'API injoignable —
     * l'enrichissement est un confort, jamais bloquant.
     */
    public function detailsPlace(string $latitude, string $longitude): ?PlaceAttributs
    {
        if (!$this->isConfigured() || '' === trim($latitude) || '' === trim($longitude)) {
            return null;
        }
        try {
            $donnees = $this->requete('GET', '/v2/place-details', [
                'lat' => trim($latitude),
                'lon' => trim($longitude),
                'features' => 'details',
            ], attendus: [200]);
        } catch (\RuntimeException) {
            return null;
        }
        $features = $donnees['features'] ?? null;
        if (!is_array($features) || [] === $features) {
            return null;
        }
        // Tags OSM bruts fusionnés de tous les features renvoyés (bâtiment +
        // POI) ; la première valeur rencontrée pour une clé l'emporte.
        $raw = [];
        foreach ($features as $feature) {
            $brut = $feature['properties']['datasource']['raw'] ?? null;
            if (is_array($brut)) {
                $raw += $brut;
            }
        }
        if ([] === $raw) {
            return null;
        }

        return self::extraireAttributs($raw);
    }

    /**
     * @param array<string, mixed> $raw tags OSM bruts
     */
    private static function extraireAttributs(array $raw): PlaceAttributs
    {
        $tag = static fn (string $cle): ?string => is_string($raw[$cle] ?? null) && '' !== trim($raw[$cle]) ? strtolower(trim($raw[$cle])) : null;
        $cuisines = null === $tag('cuisine') ? [] : array_values(array_filter(array_map('trim', explode(';', $tag('cuisine')))));
        $regimes = [];
        foreach (['vegan', 'vegetarian', 'halal', 'kosher'] as $regime) {
            if (in_array($tag('diet:'.$regime), ['yes', 'only'], true)) {
                $regimes[] = $regime;
            }
        }
        if (in_array($tag('organic'), ['yes', 'only'], true)) {
            $regimes[] = 'organic';
        }

        return new PlaceAttributs(
            cuisines: $cuisines,
            regimes: $regimes,
            accesPmr: self::triState($tag('wheelchair'), ['yes', 'limited', 'designated']),
            toilettesPmr: self::triState($tag('toilets:wheelchair'), ['yes']),
            terrasse: self::triState($tag('outdoor_seating'), ['yes']),
            climatisation: self::triState($tag('air_conditioning'), ['yes']),
            wifi: self::triState($tag('internet_access'), ['yes', 'wlan', 'wifi']),
            siteWeb: self::premiereChaine($raw, ['website', 'contact:website', 'url']),
        );
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<string>          $cles
     */
    private static function premiereChaine(array $raw, array $cles): ?string
    {
        foreach ($cles as $cle) {
            if (is_string($raw[$cle] ?? null) && '' !== trim($raw[$cle])) {
                return trim($raw[$cle]);
            }
        }

        return null;
    }

    /** @param list<string> $vrais valeurs OSM comptant pour « oui » ; « no » vaut faux, le reste null (inconnu). */
    private static function triState(?string $valeur, array $vrais): ?bool
    {
        if (null === $valeur) {
            return null;
        }
        if (in_array($valeur, $vrais, true)) {
            return true;
        }

        return 'no' === $valeur ? false : null;
    }

    public function verifierLot(array $lignes): array
    {
        if ([] === $lignes) {
            return [];
        }
        // Un job par pays : le filtre countrycode s'applique au job entier.
        $parPays = [];
        foreach ($lignes as $ligne) {
            $parPays[strtolower($ligne['pays'] ?? '')][] = $ligne;
        }
        $resultats = [];
        foreach ($parPays as $pays => $groupe) {
            if ('' === $pays) {
                continue;
            }
            foreach (array_chunk($groupe, self::TAILLE_JOB) as $tranche) {
                $resultats += 1 === count($tranche)
                    ? $this->simple($tranche[0], $pays)
                    : $this->batch($tranche, $pays);
            }
        }

        return $resultats;
    }

    /**
     * @param array{id: string, adresse: string, codePostal: string, ville: string, pays?: string} $ligne
     *
     * @return array<array-key, array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}>
     */
    private function simple(array $ligne, string $pays): array
    {
        $donnees = $this->requete('GET', '/v1/geocode/search', [
            'text' => self::texte($ligne),
            'filter' => 'countrycode:'.$pays,
            'limit' => '1',
            'format' => 'json',
        ]);
        $resultat = $donnees['results'][0] ?? null;

        return is_array($resultat) ? [$ligne['id'] => $this->mapper($resultat, $pays)] : [];
    }

    /**
     * Job asynchrone : soumission (202 + id), puis interrogation jusqu'au 200.
     * Les résultats reviennent dans l'ordre de soumission.
     *
     * @param list<array{id: string, adresse: string, codePostal: string, ville: string, pays?: string}> $tranche
     *
     * @return array<array-key, array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}>
     */
    private function batch(array $tranche, string $pays): array
    {
        $soumission = $this->requete('POST', '/v1/batch/geocode/search', [
            'filter' => 'countrycode:'.$pays,
            'format' => 'json',
        ], array_map(self::texte(...), $tranche));
        $jobId = $soumission['id'] ?? null;
        if (!is_string($jobId) || '' === $jobId) {
            throw new \RuntimeException('Geoapify n\'a pas retourné d\'identifiant de job batch.');
        }
        for ($essai = 0; $essai < self::ESSAIS_POLLING; ++$essai) {
            $donnees = $this->requete('GET', '/v1/batch/geocode/search', ['id' => $jobId, 'format' => 'json'], attendus: [200, 202]);
            // Résultat : liste nue ou enveloppe {results: [...]}, dans
            // l'ordre de soumission. Tout autre corps = job encore en cours.
            $liste = null === $donnees ? null : (array_is_list($donnees) ? $donnees : ($donnees['results'] ?? null));
            if (is_array($liste) && array_is_list($liste)) {
                $resultats = [];
                foreach ($tranche as $index => $ligne) {
                    $resultat = $liste[$index] ?? null;
                    if (is_array($resultat)) {
                        $resultats[$ligne['id']] = $this->mapper($resultat, $pays);
                    }
                }

                return $resultats;
            }
            sleep(max(0, $this->intervallePolling));
        }

        throw new \RuntimeException(sprintf('Le job batch Geoapify %s n\'a pas abouti dans le délai imparti.', $jobId));
    }

    /**
     * @param array<string, string> $query
     * @param mixed                 $json     corps JSON éventuel (POST)
     * @param list<int>             $attendus codes HTTP acceptés ; 202 (job en cours) → null
     *
     * @return array<array-key, mixed>|null
     */
    private function requete(string $method, string $chemin, array $query, mixed $json = null, array $attendus = [200, 202]): ?array
    {
        $options = ['query' => $query + ['apiKey' => $this->apiKey]];
        if (null !== $json) {
            $options['json'] = $json;
        }
        try {
            $response = $this->httpClient->request($method, rtrim($this->endpoint, '/').$chemin, $options);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Geoapify est injoignable.', 0, $exception);
        }
        if (!in_array($status, $attendus, true)) {
            throw new \RuntimeException(sprintf('Geoapify a répondu HTTP %d.', $status));
        }
        if (202 === $status && 'GET' === $method) {
            return null;
        }
        $donnees = json_decode($body, true);

        return is_array($donnees) ? $donnees : null;
    }

    /**
     * Shape commun des vérificateurs. Un résultat hors du pays demandé (le
     * filtre a des trous sur certains types) vaut « aucun résultat fiable ».
     *
     * @param array<array-key, mixed> $resultat
     *
     * @return array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}
     */
    private function mapper(array $resultat, string $pays): array
    {
        $vide = ['score' => null, 'label' => null, 'name' => null, 'codePostal' => null, 'ville' => null, 'latitude' => null, 'longitude' => null, 'type' => null];
        $paysResultat = $resultat['country_code'] ?? null;
        if (!is_string($paysResultat) || strtolower($paysResultat) !== $pays) {
            return $vide;
        }
        $texte = static fn (string $cle): ?string => is_string($resultat[$cle] ?? null) && '' !== trim($resultat[$cle]) ? trim($resultat[$cle]) : null;
        $type = $texte('result_type');

        return [
            'score' => is_numeric($resultat['rank']['confidence'] ?? null) ? (float) $resultat['rank']['confidence'] : null,
            'label' => $texte('formatted'),
            'name' => $texte('address_line1'),
            'codePostal' => $texte('postcode'),
            'ville' => $texte('city'),
            'latitude' => is_numeric($resultat['lat'] ?? null) ? (string) $resultat['lat'] : null,
            'longitude' => is_numeric($resultat['lon'] ?? null) ? (string) $resultat['lon'] : null,
            // Aligné sur les niveaux BAN : l'acceptation un clic ne réécrit
            // la rue qu'au niveau rue/numéro.
            'type' => match ($type) {
                'building', 'amenity' => 'housenumber',
                default => $type,
            },
        ];
    }

    /** @param array{id: string, adresse: string, codePostal: string, ville: string, pays?: string} $ligne */
    private static function texte(array $ligne): string
    {
        return trim(trim(sprintf('%s, %s %s', $ligne['adresse'], $ligne['codePostal'], $ligne['ville'])), ' ,');
    }
}
