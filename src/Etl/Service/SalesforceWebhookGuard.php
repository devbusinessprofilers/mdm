<?php

declare(strict_types=1);

namespace App\Etl\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contrôle d'accès du webhook produit Salesforce : jeton Bearer statique
 * SALESFORCE_WEBHOOK_TOKEN, indépendant de la connexion OAuth sortante
 * (SALESFORCE_*). Vide = webhook désactivé.
 */
final readonly class SalesforceWebhookGuard
{
    public function __construct(
        #[Autowire(env: 'SALESFORCE_WEBHOOK_TOKEN')]
        private string $token,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->token;
    }

    public function isAuthorized(Request $request): bool
    {
        if ('' === $this->token) {
            return false;
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', $request->headers->get('Authorization', ''), $matches)) {
            return false;
        }

        return hash_equals($this->token, $matches[1]);
    }
}
