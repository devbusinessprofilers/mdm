<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Enum\TypeAccesRestaurant;

/** @extends AbstractAccesType<RestaurantAcces> */
final class RestaurantAccesType extends AbstractAccesType
{
    protected function classeAcces(): string
    {
        return RestaurantAcces::class;
    }

    protected function typesAcces(): array
    {
        return [
            'Aéroport' => TypeAccesRestaurant::Aeroport,
            'Gare' => TypeAccesRestaurant::Gare,
            'Métro' => TypeAccesRestaurant::Metro,
            'Tramway' => TypeAccesRestaurant::Tramway,
            'Accès par la route' => TypeAccesRestaurant::GrandeVille,
        ];
    }
}
