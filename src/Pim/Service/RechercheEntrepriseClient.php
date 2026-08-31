<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Interroge l'annuaire public des entreprises (recherche-entreprises.api.gouv.fr)
 * pour pré-remplir une fiche à la création et contrôler l'état des
 * établissements. Les appels sont lissés sous la limite publique de 7 req/s ;
 * une indisponibilité (réseau, quota, 5xx) lève
 * EnrichissementIndisponibleException — absorbée au pré-remplissage (confort,
 * jamais bloquant), propagée aux scans d'enrichissement qui doivent la
 * distinguer d'un « aucun résultat ».
 */
#[WithMonologChannel('enrichment')]
final class RechercheEntrepriseClient
{
    /** L'API publique est limitée à 7 req/s : on lisse en dessous. */
    private const INTERVALLE_MIN_SECONDES = 0.16;

    private float $derniereRequete = 0.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $endpoint,
    ) {}

    /**
     * @param bool $absorberIndisponibilite vrai au pré-remplissage (une panne
     *                                      rend null) ; les scans passent faux
     *
     * @throws EnrichissementIndisponibleException
     */
    public function findBest(string $query, ?string $codePostal = null, bool $absorberIndisponibilite = true): ?EntrepriseInfo
    {
        $query = trim($query);
        if ('' === $query) { return null; }
        $codePostal = trim((string) $codePostal);
        try {
            $result = $this->search($query, '' === $codePostal ? null : $codePostal);
            if (null === $result && '' !== $codePostal) {
                // Le siège peut être dans une autre commune que la fiche : on retente sans filtre.
                $result = $this->search($query, null, replisSansCodePostal: true);
            }
        } catch (EnrichissementIndisponibleException $exception) {
            if (!$absorberIndisponibilite) {
                throw $exception;
            }

            return null;
        }

        return $result;
    }

    /**
     * Recherche destinée au contrôle de statut (pas au pré-remplissage) : ne
     * filtre PAS sur les actifs et lit l'état administratif de l'établissement
     * correspondant au SIRET donné (matching_etablissements ou siège). Retourne
     * null si le SIRET est mal formé.
     *
     * @throws EnrichissementIndisponibleException
     */
    public function findStatut(string $siret): ?EntrepriseInfo
    {
        $siret = preg_replace('/\D/', '', $siret) ?? '';
        if (!preg_match('/^\d{14}$/', $siret)) { return null; }

        return $this->search($siret, null, actifsSeulement: false, siretAttendu: $siret);
    }

    /**
     * Suggestions d'adresses d'entreprises actives pour la recherche du tunnel
     * de création (la BAN et Geoapify ne connaissent pas les raisons sociales).
     * Les clés suivent les champs de Localisation, comme les suggestions
     * Geoapify ; la région INSEE n'est fournie qu'en code, elle reste vide.
     *
     * @return list<array{label: string, ruePostale: ?string, codePostal: ?string, ville: ?string, region: ?string, departement: ?string, pays: ?string, countryCode: ?string, latitude: ?string, longitude: ?string}>
     *
     * @throws EnrichissementIndisponibleException
     */
    public function suggestionsAdresse(string $query, int $limite = 3): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }
        $payload = $this->requete([
            'q' => $query,
            'page' => 1,
            'per_page' => max(1, $limite),
            'etat_administratif' => 'A',
        ]);
        $suggestions = [];
        foreach ($payload['results'] ?? [] as $result) {
            if (!\is_array($result)) {
                continue;
            }
            /** @var array<string, mixed> $siege */
            $siege = \is_array($result['siege'] ?? null) ? $result['siege'] : [];
            $denomination = self::string($result['nom_complet'] ?? null) ?? self::string($result['nom_raison_sociale'] ?? null);
            $adresse = self::string($siege['adresse'] ?? null);
            if (null === $denomination || null === $adresse) {
                continue;
            }
            $departement = self::string($siege['departement'] ?? null);
            $rue = self::nomPropre(self::rue($siege));
            $codePostal = self::string($siege['code_postal'] ?? null);
            $ville = self::nomPropre(self::string($siege['libelle_commune'] ?? null));
            // Libellé composé champ par champ : formater l'adresse d'un bloc
            // abaisserait l'article des communes (« 78170 la Celle-Saint-Cloud »).
            $affichage = trim(implode(' ', array_filter([$rue, trim(($codePostal ?? '').' '.($ville ?? ''))])));
            $suggestions[] = [
                'label' => $denomination.' — '.('' === $affichage ? self::nomPropre($adresse) : $affichage),
                'ruePostale' => $rue,
                'codePostal' => $codePostal,
                'ville' => $ville,
                'region' => null,
                'departement' => null === $departement ? null : ReferentielGeographiqueFrancais::libelleDepartement($departement),
                'pays' => 'France',
                'countryCode' => 'FR',
                'latitude' => self::string($siege['latitude'] ?? null),
                'longitude' => self::string($siege['longitude'] ?? null),
            ];
        }

        return $suggestions;
    }

    private function search(string $query, ?string $codePostal, bool $actifsSeulement = true, ?string $siretAttendu = null, bool $replisSansCodePostal = false): ?EntrepriseInfo
    {
        $parameters = ['q' => $query, 'page' => 1, 'per_page' => 1];
        if ($actifsSeulement) {
            $parameters['etat_administratif'] = 'A';
        }
        if (null !== $codePostal) {
            $parameters['code_postal'] = $codePostal;
        }
        $payload = $this->requete($parameters);
        $result = $payload['results'][0] ?? null;
        if (!\is_array($result)) { return null; }

        /** @var array<string, mixed> $siege */
        $siege = \is_array($result['siege'] ?? null) ? $result['siege'] : [];
        $siren = self::string($result['siren'] ?? null);
        $dirigeant = self::dirigeantPrincipal($result['dirigeants'] ?? null);
        $etat = self::etatAdministratif($result, $siege, $siretAttendu);

        return new EntrepriseInfo(
            denomination: self::string($result['nom_complet'] ?? null) ?? self::string($result['nom_raison_sociale'] ?? null),
            raisonSociale: self::string($result['nom_raison_sociale'] ?? null) ?? self::string($result['nom_complet'] ?? null),
            siren: $siren,
            siret: self::string($siege['siret'] ?? null),
            numeroTva: self::numeroTva($siren),
            rue: self::rue($siege),
            codePostal: self::string($siege['code_postal'] ?? null),
            ville: self::string($siege['libelle_commune'] ?? null),
            latitude: self::string($siege['latitude'] ?? null),
            longitude: self::string($siege['longitude'] ?? null),
            dirigeantPrenom: $dirigeant['prenom'] ?? null,
            dirigeantNom: $dirigeant['nom'] ?? null,
            formeJuridique: FormeJuridiqueInsee::libelle(self::string($result['nature_juridique'] ?? null)),
            etatAdministratif: $etat,
            rapprochementSansCodePostal: $replisSansCodePostal,
        );
    }

    /**
     * Une seule requête HTTP, lissée sous la limite de débit, avec un unique
     * réessai sur 429 (en respectant Retry-After).
     *
     * @param array<string, int|string> $parameters
     *
     * @return array<array-key, mixed>
     *
     * @throws EnrichissementIndisponibleException
     */
    private function requete(array $parameters): array
    {
        try {
            $response = $this->executer($parameters);
            if (429 === $response->getStatusCode()) {
                sleep(self::retryAfter($response));
                $response = $this->executer($parameters);
            }

            return $response->toArray();
        } catch (\Throwable $exception) {
            $this->logger->warning('Recherche entreprises indisponible : {message}', [
                'message' => $exception->getMessage(),
            ]);

            throw new EnrichissementIndisponibleException('L\'annuaire des entreprises est indisponible.', 0, $exception);
        }
    }

    /** @param array<string, int|string> $parameters */
    private function executer(array $parameters): ResponseInterface
    {
        $ecoule = microtime(true) - $this->derniereRequete;
        if ($ecoule < self::INTERVALLE_MIN_SECONDES) {
            usleep((int) ((self::INTERVALLE_MIN_SECONDES - $ecoule) * 1_000_000));
        }
        $this->derniereRequete = microtime(true);

        return $this->httpClient->request('GET', rtrim($this->endpoint, '/').'/search', [
            'query' => $parameters,
            'timeout' => 5,
        ]);
    }

    private static function retryAfter(ResponseInterface $response): int
    {
        $valeur = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);

        return max(1, min(30, $valeur));
    }

    /**
     * État administratif ('A' actif, 'F'/'C' cessé) : l'établissement qui
     * correspond au SIRET attendu (matching_etablissements ou siège) ; SIRET
     * attendu introuvable dans la réponse = état inconnu — jamais celui d'un
     * autre établissement, un siège fermé peut avoir un secondaire actif. Sans
     * SIRET attendu, le siège puis l'unité légale.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $siege
     */
    private static function etatAdministratif(array $result, array $siege, ?string $siretAttendu): ?string
    {
        if (null !== $siretAttendu) {
            if (\is_array($result['matching_etablissements'] ?? null)) {
                foreach ($result['matching_etablissements'] as $etablissement) {
                    if (\is_array($etablissement) && $siretAttendu === self::string($etablissement['siret'] ?? null)) {
                        return self::string($etablissement['etat_administratif'] ?? null);
                    }
                }
            }
            if ($siretAttendu === self::string($siege['siret'] ?? null)) {
                return self::string($siege['etat_administratif'] ?? null);
            }

            return null;
        }

        return self::string($siege['etat_administratif'] ?? null) ?? self::string($result['etat_administratif'] ?? null);
    }

    /**
     * Premier dirigeant personne physique : candidat signataire de la convention.
     *
     * @return array{prenom: ?string, nom: ?string}|null
     */
    private static function dirigeantPrincipal(mixed $dirigeants): ?array
    {
        if (!\is_array($dirigeants)) { return null; }
        foreach ($dirigeants as $dirigeant) {
            if (!\is_array($dirigeant) || 'personne physique' !== ($dirigeant['type_de_dirigeant'] ?? null)) {
                continue;
            }
            $nom = self::string($dirigeant['nom'] ?? null);
            // L'API concatène les prénoms ("Jean, Marie") : seul l'usuel nous intéresse.
            $prenoms = self::string($dirigeant['prenoms'] ?? null) ?? self::string($dirigeant['prenom'] ?? null);
            $prenom = null === $prenoms ? null : self::string(explode(',', $prenoms)[0]);
            if (null === $nom && null === $prenom) {
                continue;
            }

            return ['prenom' => $prenom, 'nom' => $nom];
        }

        return null;
    }

    /**
     * L'annuaire livre tout en capitales : « 1 AVENUE DU GENERAL DE GAULLE » →
     * « 1 Avenue du General de Gaulle » (particules en minuscules sauf en
     * tête). Les accents absents de la source ne sont pas restitués.
     */
    private static function nomPropre(?string $valeur): ?string
    {
        if (null === $valeur) {
            return null;
        }
        $resultat = (string) preg_replace_callback(
            "/(^|[\s\-'’])(\p{L})/u",
            static fn (array $trouve): string => $trouve[1].mb_strtoupper($trouve[2]),
            mb_strtolower($valeur),
        );

        return (string) preg_replace_callback(
            "/(?<=[\s\-'’])(De|Du|Des|La|Le|Les|L|D|Et|Sur|Sous|Au|Aux|En|Lès)(?=[\s\-'’]|$)/u",
            static fn (array $trouve): string => mb_strtolower($trouve[1]),
            $resultat,
        );
    }

    /** @param array<string, mixed> $siege */
    private static function rue(array $siege): ?string
    {
        $parts = array_filter([
            self::string($siege['numero_voie'] ?? null),
            self::string($siege['indice_repetition'] ?? null),
            self::string($siege['type_voie'] ?? null),
            self::string($siege['libelle_voie'] ?? null),
        ], static fn (?string $part): bool => null !== $part);
        if ([] === $parts) {
            return self::string($siege['adresse'] ?? null);
        }

        return implode(' ', $parts);
    }

    /** Numéro de TVA intracommunautaire français, calculable depuis le SIREN. */
    private static function numeroTva(?string $siren): ?string
    {
        if (null === $siren || !preg_match('/^\d{9}$/', $siren)) { return null; }
        $key = (12 + 3 * ((int) $siren % 97)) % 97;

        return sprintf('FR%02d%s', $key, $siren);
    }

    private static function string(mixed $value): ?string
    {
        if (\is_int($value) || \is_float($value)) { $value = (string) $value; }
        if (!\is_string($value)) { return null; }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
