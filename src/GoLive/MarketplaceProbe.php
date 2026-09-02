<?php

declare(strict_types=1);

namespace App\GoLive;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Test d'authentification autonome (POST /api/login_check) : volontairement
 * dupliqué de MarketplaceApiClient pour rester confiné dans src/GoLive/ —
 * l'outillage se supprime sans toucher le cœur.
 */
#[AsAlias(MarketplaceProbeInterface::class)]
final readonly class MarketplaceProbe implements MarketplaceProbeInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(MARKETPLACE_SYNC_API_URL)%')] private string $endpoint,
        #[Autowire('%env(MARKETPLACE_SYNC_API_LOGIN)%')] private string $login,
        #[Autowire('%env(MARKETPLACE_SYNC_API_PASSWORD)%')] private string $password,
    ) {
    }

    public function verifier(): EtapeEtat
    {
        if ('' === trim($this->endpoint)) {
            return new EtapeEtat(EtapeStatut::NonConfiguree, 'MARKETPLACE_SYNC_API_URL vide');
        }
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->endpoint, '/').'/api/login_check',
                ['json' => ['login' => $this->login, 'password' => $this->password]],
            );
            $status = $response->getStatusCode();
            $token = 200 === $status ? ($response->toArray(false)['token'] ?? null) : null;
        } catch (ExceptionInterface $e) {
            return new EtapeEtat(EtapeStatut::Bloquee, 'marketplace injoignable : '.$e->getMessage());
        }
        if (200 !== $status || !is_string($token) || '' === $token) {
            return new EtapeEtat(EtapeStatut::Bloquee, sprintf('authentification refusée (HTTP %d) — vérifier le compte machine ROLE_PIM', $status));
        }

        return new EtapeEtat(EtapeStatut::Fait, 'authentification OK');
    }
}
