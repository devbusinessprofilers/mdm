<?php

declare(strict_types=1);

namespace App\Dam\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * URL de retour après une action de curation DAM : l'écran d'où vient
 * l'action (/medias pour les éditeurs et validateurs), sinon la supervision
 * /admin/dam — réservée aux admins.
 */
final readonly class DamActionReturnUrl
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function compute(Request $request, string $filter): string
    {
        $referer = (string) $request->headers->get('referer');
        if (str_starts_with($referer, $request->getSchemeAndHttpHost().'/')) {
            return $referer;
        }

        return $this->urls->generate('app_dam_dashboard', array_filter([
            'filter' => $filter,
            'type' => $request->query->getString('type') ?: null,
            'page' => max(1, $request->query->getInt('page', 1)),
        ]));
    }
}
