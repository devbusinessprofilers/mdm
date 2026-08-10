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

    /**
     * L'éditeur MDM est la vue unique d'une fiche : « voir » et « modifier »
     * mènent au même endroit, comme dans la maquette front.
     */
    public function showUrl(TypeFiche $type, string $id): string
    {
        return $this->editUrl($type, $id);
    }

    public function editUrl(TypeFiche $type, string $id): string
    {
        return TypeFiche::Lieu === $type
            ? $this->urlGenerator->generate('app_mdm_fiche_lieu', ['id' => $id])
            : $this->urlGenerator->generate('app_mdm_fiche_gamme', ['gamme' => self::gamme($type), 'id' => $id]);
    }

    public function historyUrl(TypeFiche $type, string $id): string
    {
        return $this->urlGenerator->generate(match ($type) {
            TypeFiche::Lieu => 'app_pim_lieu_history',
            TypeFiche::Activite => 'app_pim_activite_history',
            TypeFiche::Restaurant => 'app_pim_restaurant_history',
            TypeFiche::ServiceEvenementiel => 'app_pim_service_history',
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Type de fiche invalide.'),
        }, ['id' => $id]);
    }

    private static function gamme(TypeFiche $type): string
    {
        return match ($type) {
            TypeFiche::Restaurant => 'restaurants',
            TypeFiche::Activite => 'activites',
            TypeFiche::ServiceEvenementiel => 'services',
            default => throw new \InvalidArgumentException('Type de fiche invalide.'),
        };
    }
}
