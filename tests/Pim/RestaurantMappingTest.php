<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantPeriodeFermeture;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RestaurantMappingTest extends KernelTestCase
{
    public function testRestaurantAggregateAndRoomResourceMappings(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $restaurant = $entityManager->getClassMetadata(Restaurant::class);
        self::assertSame('pim_restaurant', $restaurant->getTableName());
        self::assertTrue($restaurant->hasAssociation('fiche'));
        self::assertTrue($restaurant->hasAssociation('salles'));
        self::assertTrue($restaurant->hasAssociation('acces'));
        self::assertTrue($restaurant->hasAssociation('periodesFermeture'));
        self::assertSame('json', $restaurant->getTypeOfField('horairesJours'));

        foreach (
            [
                RestaurantSalle::class,
                RestaurantAcces::class,
                RestaurantPeriodeFermeture::class,
            ] as $entity
        ) {
            self::assertTrue(
                $entityManager->getClassMetadata($entity)
                    ->hasAssociation('restaurant'),
            );
        }

        $resource = $entityManager->getClassMetadata(RessourceLieu::class);
        self::assertTrue($resource->hasAssociation('restaurantSalle'));
    }
}
