<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class AdminPageCatalog
{
    private const LABELS = [
        'app_pim_admin' => 'Sommaire d’administration',
        'app_pim_home' => 'Accueil du PIM',
        'app_pim_global_search' => 'Recherche globale du PIM',
        'app_pim_fiche_index' => 'Liste de toutes les fiches',
        'app_pim_lieu_index' => 'Liste des lieux',
        'app_pim_lieu_new' => 'Créer un lieu',
        'app_pim_lieu_show' => 'Fiche d’un lieu',
        'app_pim_lieu_edit' => 'Modifier un lieu',
        'app_pim_activite_index' => 'Liste des activités',
        'app_pim_activite_new' => 'Créer une activité',
        'app_pim_activite_show' => 'Fiche d’une activité',
        'app_pim_activite_edit' => 'Modifier une activité',
        'app_pim_activite_history' => 'Historique d’une activité',
        'app_pim_service_index' => 'Liste des services événementiels',
        'app_pim_service_new' => 'Créer un service événementiel',
        'app_pim_service_show' => 'Fiche d’un service événementiel',
        'app_pim_service_edit' => 'Modifier un service événementiel',
        'app_pim_service_history' => 'Historique d’un service événementiel',
        'app_pim_restaurant_index' => 'Liste des restaurants',
        'app_pim_restaurant_new' => 'Créer un restaurant',
        'app_pim_restaurant_show' => 'Fiche d’un restaurant',
        'app_pim_restaurant_edit' => 'Modifier un restaurant',
        'app_pim_restaurant_history' => 'Historique d’un restaurant',
        'app_account_admin_index' => 'Collaborateurs',
        'app_account_admin_show' => 'Fiche d’un collaborateur',
        'app_account_user_admin_index' => 'Utilisateurs internes',
        'app_dam_dashboard' => 'Supervision DAM',
        'api_doc' => 'Documentation API Platform',
        'api_entrypoint' => 'Point d’entrée REST',
        'app_health' => 'Santé de l’application',
    ];

    public function __construct(private RouterInterface $router)
    {
    }

    /**
     * @return array<string, list<array{name: string, label: string, path: string, methods: string, url: string|null, parameters: list<string>}>>
     */
    public function groups(): array
    {
        $groups = ['PIM' => [], 'Administration' => [], 'API Platform' => [], 'Technique' => []];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            if (!$this->isVisible($name, $route->getPath(), $route->getDefault('_api_resource_class'))) {
                continue;
            }
            $methods = $route->getMethods();
            if ([] !== $methods && !in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
                continue;
            }
            $parameters = array_values(array_filter(
                $route->compile()->getVariables(),
                static fn (string $parameter): bool => !array_key_exists($parameter, $route->getDefaults()),
            ));
            $url = null;
            if ([] === $parameters) {
                try {
                    $url = $this->router->generate($name, [], UrlGeneratorInterface::ABSOLUTE_PATH);
                } catch (\Throwable) {
                    // The route remains documented even if its URL cannot be generated.
                }
            }
            $group = $this->group($name, $route->getPath());
            $groups[$group][] = [
                'name' => $name,
                'label' => self::LABELS[$name] ?? $this->humanize($name),
                'path' => $route->getPath(),
                'methods' => [] === $methods ? 'Tous' : implode(', ', $methods),
                'url' => $url,
                'parameters' => $parameters,
            ];
        }

        foreach ($groups as &$pages) {
            usort($pages, static fn (array $a, array $b): int => [$a['path'], $a['name']] <=> [$b['path'], $b['name']]);
        }

        return array_filter($groups);
    }

    private function isVisible(string $name, string $path, mixed $apiResource): bool
    {
        if ('app_health' === $name || 'app_pim_home' === $name) {
            return true;
        }
        if (str_starts_with($name, 'app_pim_') || str_starts_with($name, 'app_account_admin_') || 'app_dam_dashboard' === $name) {
            return true;
        }
        if (str_starts_with($path, '/api')) {
            return str_starts_with($name, 'api_') || is_string($apiResource) && str_starts_with($apiResource, 'App\\');
        }

        return false;
    }

    private function group(string $name, string $path): string
    {
        if (str_starts_with($path, '/api')) {
            return 'API Platform';
        }
        if (str_starts_with($name, 'app_account_admin_')) {
            return 'Administration';
        }
        if ('app_health' === $name) {
            return 'Technique';
        }

        return 'PIM';
    }

    private function humanize(string $name): string
    {
        $name = preg_replace('/^(?:app_|api_|_api_)/', '', $name) ?? $name;

        return ucfirst(str_replace(['_', '\\/'], ' ', $name));
    }
}
