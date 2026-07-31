<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\RestaurantType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class RestaurantTypeTest extends KernelTestCase
{
    public function testBibleFieldsUseSymfonyFormTypesAndExcludeDeferredFields(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $restaurant = new Restaurant();
        $form = $factory->create(RestaurantType::class, $restaurant, [
            'csrf_protection' => false,
        ]);

        foreach (
            [
                'typesRestaurant',
                'typesCuisine',
                'specificitesAlimentaires',
                'typesEvenement',
                'joursOuverture',
                'localisation',
                'acces',
                'salles',
                'services',
                'equipements',
                'engagementsRse',
                'ressources',
                'menus',
                'supportsCommerciaux',
            ] as $field
        ) {
            self::assertTrue($form->has($field), $field);
        }

        self::assertFalse($form->has('typeForfait'));
        self::assertFalse($form->has('nomPersonnalise'));
    }
}
