<?php

declare(strict_types=1);

namespace App\Audit;

use App\Account\Security\ExternalSitePrincipal;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;

final readonly class AuditContext
{
    public function __construct(
        private Security $security,
        private RequestStack $requests,
    ) {
    }

    /** @return array{source: string, actor: string, rolesScopes: list<string>, correlationId: string, action: ?string} */
    public function current(): array
    {
        $request = $this->requests->getCurrentRequest();
        $user = $this->security->getUser();
        $rolesScopes = array_values($user?->getRoles() ?? []);
        if ($user instanceof ExternalSitePrincipal) {
            $rolesScopes = array_values(
                array_unique([...$rolesScopes, ...$user->scopes()]),
            );
        }
        $explicitAction = $request?->attributes->get('_audit_action');
        $explicitSource = $request?->attributes->get('_audit_source');
        $source = is_string($explicitSource) && '' !== $explicitSource
            ? $explicitSource
            : (null === $request
                ? 'worker'
                : (str_starts_with($request->getPathInfo(), '/api/') ? 'external_api' : 'pim'));

        return [
            'source' => $source,
            'actor' => $user?->getUserIdentifier() ?? 'system',
            'rolesScopes' => $rolesScopes,
            'correlationId' => $request?->headers->get('X-Correlation-ID') ??
                $request?->attributes->getString('_audit_correlation_id') ?:
                (string) new Ulid(),
            'action' => is_string($explicitAction) && '' !== $explicitAction
                ? $explicitAction
                : null,
        ];
    }
}
