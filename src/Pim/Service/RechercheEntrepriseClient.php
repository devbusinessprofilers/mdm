<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Interroge l'annuaire public des entreprises (recherche-entreprises.api.gouv.fr)
 * pour pré-remplir une fiche à la création. Toute erreur (réseau, quota, réponse
 * inattendue) est absorbée : l'enrichissement est un confort, jamais bloquant.
 */
final readonly class RechercheEntrepriseClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $endpoint,
    ) {}

    public function findBest(string $query, ?string $codePostal = null): ?EntrepriseInfo
    {
        $query = trim($query);
        if ('' === $query) { return null; }
        $codePostal = trim((string) $codePostal);
        $result = $this->search($query, '' === $codePostal ? null : $codePostal);
        if (null === $result && '' !== $codePostal) {
            // Le siège peut être dans une autre commune que la fiche : on retente sans filtre.
            $result = $this->search($query, null);
        }

        return $result;
    }

    private function search(string $query, ?string $codePostal): ?EntrepriseInfo
    {
        $parameters = ['q' => $query, 'page' => 1, 'per_page' => 1, 'etat_administratif' => 'A'];
        if (null !== $codePostal) {
            $parameters['code_postal'] = $codePostal;
        }
        try {
            $payload = $this->httpClient->request('GET', rtrim($this->endpoint, '/').'/search', [
                'query' => $parameters,
                'timeout' => 5,
            ])->toArray();
        } catch (\Throwable $exception) {
            $this->logger->warning('Recherche entreprises indisponible : {message}', [
                'message' => $exception->getMessage(),
                'query' => $query,
            ]);

            return null;
        }
        $result = $payload['results'][0] ?? null;
        if (!\is_array($result)) { return null; }

        /** @var array<string, mixed> $siege */
        $siege = \is_array($result['siege'] ?? null) ? $result['siege'] : [];
        $siren = self::string($result['siren'] ?? null);
        $dirigeant = self::dirigeantPrincipal($result['dirigeants'] ?? null);

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
        );
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
