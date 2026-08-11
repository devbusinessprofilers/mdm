<?php

declare(strict_types=1);

namespace App\Etl\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $endpoint,
        private readonly string $login,
        private readonly string $password,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->endpoint);
    }

    public function upsertFiche(int $code, array $payload): void
    {
        $status = $this->authenticatedRequest(
            'PUT',
            sprintf('/api/pim/fiches/%d', $code),
            ['json' => $payload],
        );
        if (409 === $status) {
            $this->logger->info('Fiche ignorée par la marketplace : elle détient déjà une séquence plus récente.', [
                'code' => $code,
            ]);

            return;
        }
        if ($status < 200 || $status >= 300) {
            throw new MarketplaceApiException(sprintf('La marketplace a refusé la fiche %d (HTTP %d).', $code, $status));
        }
    }

    public function removeFiche(int $code, string $sequence): void
    {
        $status = $this->authenticatedRequest(
            'DELETE',
            sprintf('/api/pim/fiches/%d', $code),
            ['query' => ['sequence' => $sequence]],
        );
        if (404 === $status || 409 === $status) {
            return;
        }
        if ($status < 200 || $status >= 300) {
            throw new MarketplaceApiException(sprintf('La marketplace a refusé la dépublication de la fiche %d (HTTP %d).', $code, $status));
        }
    }

    /** @param array<string, mixed> $options */
    private function authenticatedRequest(
        string $method,
        string $path,
        array $options,
    ): int {
        if (!$this->isConfigured()) {
            throw new MarketplaceApiException('MARKETPLACE_SYNC_API_URL n\'est pas configurée.');
        }
        $status = $this->send($method, $path, $options, $this->obtainToken());
        // Jeton expiré entre deux appels du worker : une seule ré-authentification.
        if (401 === $status) {
            $this->token = null;
            $status = $this->send($method, $path, $options, $this->obtainToken());
        }

        return $status;
    }

    /** @param array<string, mixed> $options */
    private function send(
        string $method,
        string $path,
        array $options,
        string $token,
    ): int {
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['Authorization' => 'Bearer '.$token],
        );
        try {
            return $this->httpClient
                ->request($method, rtrim($this->endpoint, '/').$path, $options)
                ->getStatusCode();
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
