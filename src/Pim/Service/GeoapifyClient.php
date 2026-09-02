<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\String\u;

/**
 * Client Geoapify (géocodage mondial sur données OpenStreetMap) pour les
 * adresses hors de France — et pour la recherche d'adresse du tunnel de
 * création, tous pays. Une adresse passe par l'endpoint simple ; un lot
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
    /** Le plan gratuit est limité à 5 req/s : on lisse en dessous. */
    private const INTERVALLE_MIN_SECONDES = 0.25;

    private readonly HttpClientInterface $httpClient;
    private float $derniereRequete = 0.0;

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
     * Details). Retourne null si le lieu est introuvable ; avec un nom attendu,
     * seuls les features dont le nom OSM correspond sont retenus — le GPS d'une
     * fiche géocodée au niveau rue pointe facilement sur le commerce voisin.
     *
     * @throws EnrichissementIndisponibleException quand l'API est injoignable
     *                                             ou sous quota
     */
    public function detailsPlace(string $latitude, string $longitude, ?string $nomAttendu = null): ?PlaceAttributs
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
        } catch (\RuntimeException $exception) {
            throw new EnrichissementIndisponibleException('Geoapify Place Details est indisponible.', 0, $exception);
        }
        $features = $donnees['features'] ?? null;
        if (!is_array($features) || [] === $features) {
            return null;
        }
        $nomAttendu = null === $nomAttendu || '' === trim($nomAttendu) ? null : trim($nomAttendu);
        // Tags OSM bruts fusionnés des features retenus ; la première valeur
        // rencontrée pour une clé l'emporte.
        $raw = [];
        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $brut = $feature['properties']['datasource']['raw'] ?? null;
            if (!is_array($brut)) {
                continue;
            }
            if (null !== $nomAttendu && !self::nomCorrespond($nomAttendu, $feature, $brut)) {
                continue;
            }
            $raw += $brut;
        }
        if ([] === $raw) {
            return null;
        }

        return self::extraireAttributs($raw);
    }

    /**
     * POI autour d'un point (Places) : gares, stations de métro ou de
     * tramway… dans le rayon donné, du plus proche au plus lointain (biais
     * de proximité). Les features sans nom sont écartés — une suggestion
     * d'accès anonyme n'aide personne.
     *
     * @param list<string> $categories catégories Geoapify (public_transport.train…)
     *
     * @return list<array{nom: string, latitude: string, longitude: string, distanceMetres: ?int, categories: list<string>}>
     *
     * @throws EnrichissementIndisponibleException quand l'API est injoignable
     *                                             ou sous quota
     */
    public function poisProches(string $latitude, string $longitude, array $categories, int $rayonMetres, int $limite = 20): array
    {
        $lat = trim($latitude);
        $lon = trim($longitude);
        if (!$this->isConfigured() || '' === $lat || '' === $lon || [] === $categories) {
            return [];
        }
        try {
            $donnees = $this->requete('GET', '/v2/places', [
                'categories' => implode(',', $categories),
                'filter' => sprintf('circle:%s,%s,%d', $lon, $lat, $rayonMetres),
                'bias' => sprintf('proximity:%s,%s', $lon, $lat),
                'limit' => (string) max(1, $limite),
                'lang' => 'fr',
            ], attendus: [200]);
        } catch (\RuntimeException $exception) {
            throw new EnrichissementIndisponibleException('Geoapify Places est indisponible.', 0, $exception);
        }
        $pois = [];
        foreach ($donnees['features'] ?? [] as $feature) {
            $props = is_array($feature) ? ($feature['properties'] ?? null) : null;
            if (!is_array($props)) {
                continue;
            }
            $nom = is_string($props['name'] ?? null) ? trim($props['name']) : '';
            if ('' === $nom || !is_numeric($props['lat'] ?? null) || !is_numeric($props['lon'] ?? null)) {
                continue;
            }
            $pois[] = [
                'nom' => $nom,
                'latitude' => (string) $props['lat'],
                'longitude' => (string) $props['lon'],
                'distanceMetres' => is_numeric($props['distance'] ?? null) ? (int) round((float) $props['distance']) : null,
                'categories' => array_values(array_filter((array) ($props['categories'] ?? []), is_string(...))),
            ];
        }

        return $pois;
    }

    /**
     * Itinéraire d'un point à un autre (Routing) : distance routière et durée
     * pour un mode (`drive`, `walk`…). Null quand aucun itinéraire n'existe —
     * Geoapify répond alors 400, accepté ici comme un résultat vide.
     *
     * @return array{distanceMetres: int, dureeSecondes: int}|null
     *
     * @throws EnrichissementIndisponibleException quand l'API est injoignable
     *                                             ou sous quota
     */
    public function itineraire(string $deLatitude, string $deLongitude, string $versLatitude, string $versLongitude, string $mode = 'drive'): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        try {
            $donnees = $this->requete('GET', '/v1/routing', [
                'waypoints' => sprintf('%s,%s|%s,%s', trim($deLatitude), trim($deLongitude), trim($versLatitude), trim($versLongitude)),
                'mode' => $mode,
            ], attendus: [200, 400]);
        } catch (\RuntimeException $exception) {
            throw new EnrichissementIndisponibleException('Geoapify Routing est indisponible.', 0, $exception);
        }
        $props = $donnees['features'][0]['properties'] ?? null;
        if (!is_array($props) || !is_numeric($props['distance'] ?? null) || !is_numeric($props['time'] ?? null)) {
            return null;
        }

        return [
            'distanceMetres' => (int) round((float) $props['distance']),
            'dureeSecondes' => (int) round((float) $props['time']),
        ];
    }

    /**
     * @param array<array-key, mixed> $feature
     * @param array<array-key, mixed> $brut
     */
    private static function nomCorrespond(string $nomAttendu, array $feature, array $brut): bool
    {
        $nom = $feature['properties']['name'] ?? $brut['name'] ?? null;

        return is_string($nom) && NomSimilarite::score($nomAttendu, $nom) >= NomSimilarite::SEUIL_DEFAUT;
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
            // `brand` seulement : `operator` est l'exploitant, pas l'enseigne.
            marque: self::premiereChaine($raw, ['brand']),
            etoiles: self::etoiles($tag('stars')),
            // Un `swimming_pool=yes` sans type ne permet pas de choisir entre
            // les deux entrées de la liste bien-être : on s'abstient.
            piscineInterieure: 'indoor' === $tag('swimming_pool') ? true : null,
            piscineExterieure: 'outdoor' === $tag('swimming_pool') ? true : null,
            sauna: self::triState($tag('sauna'), ['yes']),
            spa: self::triState($tag('spa'), ['yes']),
            jardin: 'garden' === $tag('leisure') ? true : self::triState($tag('garden'), ['yes']),
            // `parking` porte le type d'aire (surface, underground…) : toute
            // valeur autre que « no » signale une offre de stationnement.
            parking: null === $tag('parking') ? null : 'no' !== $tag('parking'),
            ascenseur: self::triState($tag('elevator'), ['yes']),
            chambres: self::chambres($tag('rooms')),
            horairesOuverture: self::premiereChaine($raw, ['opening_hours']),
            categorie: $tag('tourism') ?? $tag('amenity'),
        );
    }

    /** Nombre de chambres OSM `rooms` : entier plausible 1..2000, sinon null. */
    private static function chambres(?string $valeur): ?int
    {
        if (null === $valeur || 1 !== preg_match('/^\d{1,4}$/', $valeur)) {
            return null;
        }
        $nombre = (int) $valeur;

        return $nombre >= 1 && $nombre <= 2000 ? $nombre : null;
    }

    /** Classement OSM `stars` : « 4 » ou « 4S » (supérieur) → 4 ; hors 1..5 → null. */
    private static function etoiles(?string $valeur): ?int
    {
        if (null === $valeur || 1 !== preg_match('/^([1-5])s?$/', $valeur, $trouve)) {
            return null;
        }

        return (int) $trouve[1];
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

    /** Adresses (flux texte) retenues au plus par recherche. */
    private const LIMITE_ADRESSES = 4;
    /** Établissements (flux nom) retenus au plus par recherche. */
    private const LIMITE_ETABLISSEMENTS = 3;
    /** Confiance Geoapify en deçà de laquelle une adresse est écartée. */
    private const CONFIANCE_PLANCHER = 0.4;

    /**
     * Autocomplétion pour la recherche du tunnel de création. Le texte saisi
     * et le nom de la fiche sont cherchés SÉPARÉMENT puis fusionnés (texte
     * d'abord : c'est le signal le plus fort) : les concaténer étouffe le
     * géocodeur — « The Landmark London 222 » sort un homonyme de Canary Wharf
     * et jamais le 222 Marylebone Road, que le texte seul trouve avec son
     * numéro. Les adresses sont triées par confiance ; les établissements dont
     * le nom OSM ne correspond pas à la fiche (homonymes) sont écartés.
     *
     * @return list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, source: string}>
     */
    public function autocompleteFiche(string $nom, string $texte, string $pays, int $limite = 5): array
    {
        $nom = trim($nom);
        $texte = trim($texte);
        if ('' === $texte) {
            return self::livrer(self::fluxEtablissements($this->autocomplete($nom, $pays, $limite), $nom), 'etablissement');
        }
        $suggestions = self::livrer(self::fluxAdresses($this->autocomplete($texte, $pays, $limite)), 'adresse');
        if ('' !== $nom) {
            $labels = array_column($suggestions, 'label');
            $etablissements = self::fluxEtablissements($this->autocomplete($nom, $pays, $limite), $nom);
            foreach (self::livrer($etablissements, 'etablissement') as $suggestion) {
                if (!in_array($suggestion['label'], $labels, true)) {
                    $suggestions[] = $suggestion;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Flux texte : adresses au-dessus du plancher de confiance (une confiance
     * inconnue passe), triées de la plus sûre à la moins sûre, plafonnées.
     *
     * @param list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, score: ?float, nom: ?string}> $suggestions
     *
     * @return list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, score: ?float, nom: ?string}>
     */
    private static function fluxAdresses(array $suggestions): array
    {
        $suggestions = array_values(array_filter(
            $suggestions,
            static fn (array $suggestion): bool => null === $suggestion['score'] || $suggestion['score'] >= self::CONFIANCE_PLANCHER,
        ));
        usort($suggestions, static fn (array $a, array $b): int => ($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0));

        return array_slice($suggestions, 0, self::LIMITE_ADRESSES);
    }

    /**
     * Flux nom : seuls les résultats dont le nom OSM correspond à la fiche
     * restent — similarité suffisante, ou nom de fiche contenu dans le nom du
     * résultat. Une seule direction d'inclusion : l'inverse garderait
     * l'homonyme (« The Landmark » ⊂ « The Landmark London »).
     *
     * @param list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, score: ?float, nom: ?string}> $suggestions
     *
     * @return list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, score: ?float, nom: ?string}>
     */
    private static function fluxEtablissements(array $suggestions, string $nom): array
    {
        $fiche = u($nom)->trim()->lower()->ascii()->toString();
        $suggestions = array_values(array_filter($suggestions, static function (array $suggestion) use ($nom, $fiche): bool {
            $nomResultat = $suggestion['nom'];
            if (null === $nomResultat || '' === $fiche) {
                return false;
            }
            if (NomSimilarite::score($nom, $nomResultat) >= NomSimilarite::SEUIL_DEFAUT) {
                return true;
            }

            return str_contains(u($nomResultat)->trim()->lower()->ascii()->toString(), $fiche);
        }));

        return array_slice($suggestions, 0, self::LIMITE_ETABLISSEMENTS);
    }

    /**
     * Retire les clés internes de filtrage et étiquette la source (badge de la
     * liste de suggestions).
     *
     * @param list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, score: ?float, nom: ?string}> $suggestions
     *
     * @return list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, source: string}>
     */
    private static function livrer(array $suggestions, string $source): array
    {
        return array_map(static function (array $suggestion) use ($source): array {
            unset($suggestion['score'], $suggestion['nom']);

            return $suggestion + ['source' => $source];
        }, $suggestions);
    }

    /**
     * Suggestions d'adresses pendant la frappe (recherche du tunnel de
     * création), bornées à un pays. Les clés suivent les champs de
     * Localisation ; `score` (confiance Geoapify) et `nom` (nom OSM) sont des
     * clés internes de filtrage, retirées avant livraison par livrer().
     * `$type` restreint aux résultats d'un niveau Geoapify (`city`…) — la
     * recherche de ville de référence des sites de diffusion s'en sert.
     *
     * @return list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string, score: ?float, nom: ?string}>
     */
    public function autocomplete(string $texte, string $pays, int $limite = 5, ?string $type = null): array
    {
        $texte = trim($texte);
        $pays = strtolower(trim($pays));
        if (!$this->isConfigured() || '' === $texte || '' === $pays) {
            return [];
        }
        $donnees = $this->requete('GET', '/v1/geocode/autocomplete', array_filter([
            'text' => $texte,
            'filter' => 'countrycode:'.$pays,
            'limit' => (string) max(1, $limite),
            'lang' => 'fr',
            'format' => 'json',
            'type' => $type,
        ], static fn (?string $valeur): bool => null !== $valeur), attendus: [200]);
        $suggestions = [];
        foreach ($donnees['results'] ?? [] as $resultat) {
            if (!is_array($resultat)) {
                continue;
            }
            // Même garde que mapper() : le filtre pays a des trous sur certains types.
            $paysResultat = $resultat['country_code'] ?? null;
            if (!is_string($paysResultat) || strtolower($paysResultat) !== $pays) {
                continue;
            }
            // housenumber sort parfois en numérique du JSON Geoapify.
            $champ = static fn (string $cle): ?string => is_scalar($resultat[$cle] ?? null) && '' !== trim((string) $resultat[$cle]) ? trim((string) $resultat[$cle]) : null;
            $label = $champ('formatted');
            if (null === $label) {
                continue;
            }
            $rue = trim(sprintf('%s %s', $champ('housenumber') ?? '', $champ('street') ?? ''));
            $suggestions[] = [
                'label' => $label,
                'ruePostale' => '' === $rue ? null : $rue,
                'codePostal' => $champ('postcode'),
                'ville' => $champ('city'),
                'region' => $champ('state'),
                'departement' => $champ('county'),
                'pays' => $champ('country'),
                'countryCode' => strtoupper($paysResultat),
                'latitude' => is_numeric($resultat['lat'] ?? null) ? (string) $resultat['lat'] : null,
                'longitude' => is_numeric($resultat['lon'] ?? null) ? (string) $resultat['lon'] : null,
                'score' => is_numeric($resultat['rank']['confidence'] ?? null) ? (float) $resultat['rank']['confidence'] : null,
                'nom' => $champ('name') ?? $champ('address_line1'),
            ];
        }

        return $suggestions;
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
            $response = $this->executer($method, $chemin, $options);
            if (429 === $response->getStatusCode()) {
                // Quota dépassé malgré le lissage : un seul réessai, après Retry-After.
                sleep(self::retryAfter($response));
                $response = $this->executer($method, $chemin, $options);
            }
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            // Pas de chaînage de l'exception d'origine : son message contient
            // l'URL appelée, clé API incluse.
            throw new \RuntimeException('Geoapify est injoignable : '.$this->sansCle($exception->getMessage()));
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

    /** @param array<string, mixed> $options */
    private function executer(string $method, string $chemin, array $options): ResponseInterface
    {
        $ecoule = microtime(true) - $this->derniereRequete;
        if ($ecoule < self::INTERVALLE_MIN_SECONDES) {
            usleep((int) ((self::INTERVALLE_MIN_SECONDES - $ecoule) * 1_000_000));
        }
        $this->derniereRequete = microtime(true);

        return $this->httpClient->request($method, rtrim($this->endpoint, '/').$chemin, $options);
    }

    private static function retryAfter(ResponseInterface $response): int
    {
        $valeur = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);

        return max(1, min(30, $valeur));
    }

    private function sansCle(string $message): string
    {
        return '' === $this->apiKey ? $message : str_replace($this->apiKey, '***', $message);
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
