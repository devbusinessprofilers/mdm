<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class FicheRouteResolver
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function showUrl(TypeFiche $type, string $id): string
    {
        return $this->urlGenerator->generate(self::routesFor($type)[0], ['id' => $id]);
    }

    public function editUrl(TypeFiche $type, string $id): string
    {
        return $this->urlGenerator->generate(self::routesFor($type)[1], ['id' => $id]);
    }

    public function historyUrl(TypeFiche $type, string $id): string
    {
        return $this->urlGenerator->generate(self::routesFor($type)[2], ['id' => $id]);
    }

    /** @return array{string, string, string} */
    private static function routesFor(TypeFiche $type): array
    {
        return match ($type) {
            TypeFiche::Lieu => ['app_pim_lieu_show', 'app_pim_lieu_edit', 'app_pim_lieu_history'],
            TypeFiche::Activite => ['app_pim_activite_show', 'app_pim_activite_edit', 'app_pim_activite_history'],
            TypeFiche::Restaurant => ['app_pim_restaurant_show', 'app_pim_restaurant_edit', 'app_pim_restaurant_history'],
            TypeFiche::ServiceEvenementiel => ['app_pim_service_show', 'app_pim_service_edit', 'app_pim_service_history'],
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Type de fiche invalide.'),
        };
    }
}
