<?php

declare(strict_types=1);

namespace App\Etl\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client HTTP de l'API de synchronisation marketplace. L'authentification est
 * le JWT Lexik de la marketplace : POST /api/login_check avec le compte
 * machine dédié (ROLE_PIM), puis Bearer sur les appels. Le jeton est mis en
 * cache pour la durée de vie du worker et renouvelé sur 401.
 *
 * L'URL, le login et le mot de passe viennent de l'environnement
 * (MARKETPLACE_SYNC_API_*), différents en local / test / preprod / prod.
 */
final class MarketplaceApiClient implements MarketplaceClientInterface
{
    private ?string $token = null;

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $endpoint,
        private readonly string $login,
        private readonly string $password,
    ) {
        // Bornes dures sur chaque appel (login JWT compris) : le worker tient
        // une transaction MySQL ouverte pendant la requête, un appel lent ne
        // doit pas retenir la connexion indéfiniment.
        $this->httpClient = $httpClient->withOptions([
            'timeout' => 10,
            'max_duration' => 30,
        ]);
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->endpoint);
    }

    public function upsertFiche(int $code, array $payload): bool
    {
        $status = $this->authenticatedRequest(
            'PUT',
            sprintf('/api/pim/fiches/%d', $code),
            ['json' => $payload],
        )->getStatusCode();
        if (409 === $status) {
            return false;
        }
        if ($status < 200 || $status >= 300) {
            throw new MarketplaceApiException(
                sprintf('La marketplace a refusé la fiche %d (HTTP %d).', $code, $status),
                retryable: self::retryable($status),
            );
        }

        return true;
    }

    public function upsertLovDictionary(array $payload): bool
    {
        $status = $this->authenticatedRequest(
            'PUT',
            '/api/pim/referentiels',
            ['json' => $payload],
        )->getStatusCode();
        if (409 === $status) {
            return false;
        }
        if ($status < 200 || $status >= 300) {
            throw new MarketplaceApiException(
                sprintf('La marketplace a refusé le dictionnaire LOV (HTTP %d).', $status),
                retryable: self::retryable($status),
            );
        }

        return true;
    }

    public function removeFiche(int $code, string $sequence): bool
    {
        $status = $this->authenticatedRequest(
            'DELETE',
            sprintf('/api/pim/fiches/%d', $code),
            ['query' => ['sequence' => $sequence]],
        )->getStatusCode();
        if (409 === $status) {
            return false;
        }
        if (404 === $status) {
            return true;
        }
        if ($status < 200 || $status >= 300) {
            throw new MarketplaceApiException(
                sprintf('La marketplace a refusé la dépublication de la fiche %d (HTTP %d).', $code, $status),
                retryable: self::retryable($status),
            );
        }

        return true;
    }

    public function pruneFichePhotos(int $code, string $sequence, array $locations): array|false|null
    {
        $response = $this->authenticatedRequest(
            'PUT',
            sprintf('/api/pim/fiches/%d/photos', $code),
            ['json' => ['sequence' => $sequence, 'photos' => $locations]],
        );
        $status = $response->getStatusCode();
        if (409 === $status) {
            return false;
        }
        if (404 === $status) {
            return null;
        }
        if ($status < 200 || $status >= 300) {
            throw new MarketplaceApiException(
                sprintf('La marketplace a refusé la purge des photos de la fiche %d (HTTP %d).', $code, $status),
                retryable: self::retryable($status),
            );
        }
        try {
            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new MarketplaceApiException(
                sprintf('Réponse illisible à la purge des photos de la fiche %d.', $code),
                0,
                $exception,
            );
        }

        return [
            'removed' => (int) ($data['removed'] ?? 0),
            'remaining' => (int) ($data['remaining'] ?? 0),
            'principaleRemaining' => (bool) ($data['principaleRemaining'] ?? false),
        ];
    }

    /**
     * Un 4xx (hors cas tolérés en amont) est un refus permanent : relancer
     * rejouerait le même échec. Les 5xx et le 401 persistant après
     * ré-authentification (incident côté marketplace) restent relançables.
     */
    private static function retryable(int $status): bool
    {
        return $status >= 500 || 401 === $status;
    }

    /** @param array<string, mixed> $options */
    private function authenticatedRequest(
        string $method,
        string $path,
        array $options,
    ): ResponseInterface {
        if (!$this->isConfigured()) {
            throw new MarketplaceApiException('MARKETPLACE_SYNC_API_URL n\'est pas configurée.');
        }
        $response = $this->send($method, $path, $options, $this->obtainToken());
        // Jeton expiré entre deux appels du worker : une seule ré-authentification.
        if (401 === $response->getStatusCode()) {
            $this->token = null;
            $response = $this->send($method, $path, $options, $this->obtainToken());
        }

        return $response;
    }

    /** @param array<string, mixed> $options */
    private function send(
        string $method,
        string $path,
        array $options,
        string $token,
    ): ResponseInterface {
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['Authorization' => 'Bearer '.$token],
        );
        try {
            $response = $this->httpClient
                ->request($method, rtrim($this->endpoint, '/').$path, $options);
            // Force la résolution ici : les erreurs transport sortent en
            // MarketplaceApiException plutôt qu'au premier getStatusCode().
            $response->getStatusCode();

            return $response;
        } catch (ExceptionInterface $exception) {
            throw new MarketplaceApiException('La marketplace est injoignable.', 0, $exception);
        }
    }

    private function obtainToken(): string
    {
        if (null !== $this->token) {
            return $this->token;
        }
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->endpoint, '/').'/api/login_check',
                ['json' => ['login' => $this->login, 'password' => $this->password]],
            );
            $status = $response->getStatusCode();
            $payload = 200 === $status ? $response->toArray(false) : [];
        } catch (ExceptionInterface $exception) {
            throw new MarketplaceApiException('La marketplace est injoignable pour l\'authentification.', 0, $exception);
        }
        $token = $payload['token'] ?? null;
        if (200 !== $status || !is_string($token) || '' === $token) {
            throw new MarketplaceApiException(sprintf('Authentification marketplace refusée (HTTP %d).', $status));
        }

        return $this->token = $token;
    }
}
