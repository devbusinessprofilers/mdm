<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Les URL d'une fiche selon sa gamme : l'éditeur (une route pour les lieux,
 * une route paramétrée par le segment pour les autres gammes) et
 * l'historique. Le seul endroit qui connaît ces noms de routes.
 */
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

    /** @param ?int $section section de l'éditeur à ouvrir (index du catalogue des sections) */
    public function editUrl(TypeFiche $type, string $id, ?int $section = null): string
    {
        if (!$type->estOperationnel()) {
            throw new \InvalidArgumentException('Gamme hors de cette version du MDM.');
        }
        $params = ['id' => $id] + (null === $section ? [] : ['section' => $section]);

        return TypeFiche::Lieu === $type
            ? $this->urlGenerator->generate('app_mdm_fiche_lieu', $params)
            : $this->urlGenerator->generate('app_mdm_fiche_gamme', ['gamme' => $type->slug()] + $params);
    }

    /** La liste du référentiel de la gamme (retour après une suppression). */
    public function listeUrl(TypeFiche $type): string
    {
        return TypeFiche::Lieu === $type
            ? $this->urlGenerator->generate('app_mdm_lieux')
            : $this->urlGenerator->generate('app_mdm_referentiel_gamme', ['gamme' => $type->slug()]);
    }

    public function historyUrl(TypeFiche $type, string $id): string
    {
        return $this->urlGenerator->generate(match ($type) {
            TypeFiche::Lieu => 'app_pim_lieu_history',
            TypeFiche::Activite => 'app_pim_activite_history',
            TypeFiche::Restaurant => 'app_pim_restaurant_history',
            TypeFiche::ServiceEvenementiel => 'app_pim_service_history',
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Gamme hors de cette version du MDM.'),
        }, ['id' => $id]);
    }
}
