<?php

declare(strict_types=1);

use App\Pim\Controller\FicheWorkflowController;
use App\Pim\Enum\FicheTransition;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Les 32 routes de transition de workflow (4 gammes × 8 transitions), toutes
 * servies par FicheWorkflowController::transition. Les noms
 * `app_pim_{domaine}_{transition}` et les URL
 * `/referentiel/{gamme}/fiche/{id}/{segment}` sont ceux des anciens
 * contrôleurs par gamme : FicheActionFormFactory les génère tels quels.
 */
return static function (RoutingConfigurator $routes): void {
    foreach (TypeFiche::operationnels() as $type) {
        foreach (FicheTransition::cases() as $transition) {
            $routes
                ->add(sprintf('app_pim_%s_%s', $type->domaine(), $transition->value), sprintf('/referentiel/%s/fiche/{id}/%s', $type->slug(), $transition->segment()))
                ->controller([FicheWorkflowController::class, 'transition'])
                ->defaults(['gamme' => $type->slug(), 'transition' => $transition->value])
                ->requirements(['id' => '[0-9A-HJKMNP-TV-Z]{26}'])
                ->methods(['POST']);
        }
    }
};
