<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RestaurantValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;
    }

    public function testIncompleteDraftCanBeSavedButNotSubmitted(): void
    {
        $restaurant = new Restaurant();

        self::assertCount(
            0,
            $this->validator->validate($restaurant, null, [
                ValidationGroups::DRAFT,
            ]),
        );

        $violations = $this->validator->validate($restaurant, null, [
            ValidationGroups::SUBMISSION,
        ]);
        $paths = array_map(
            static fn (
                ConstraintViolationInterface $violation,
            ): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        );

        self::assertGreaterThan(15, count($violations));
        self::assertContains('typesRestaurant', $paths);
        self::assertContains('typesCuisine', $paths);
        self::assertContains('ressources', $paths);
        self::assertContains('localisation.pays', $paths);
    }

    public function testInvalidClosingTimeIsRejectedInDraft(): void
    {
        $restaurant = new Restaurant();
        $restaurant->changeHorairesJours([
            'LUNDI' => ['ouverture' => '18:00', 'fermeture' => '17:59'],
        ]);

        $violations = $this->validator->validate($restaurant, null, [
            ValidationGroups::DRAFT,
        ]);

        self::assertSame(
            'horairesJours',
            $violations->get(0)->getPropertyPath(),
        );
        self::assertStringContainsString('fermeture doit être postérieure', (string) $violations->get(0)->getMessage());
    }

    public function testNegativeCapacityIsRejectedInDraft(): void
    {
        $restaurant = new Restaurant();
        $restaurant->changeCapaciteCocktail(-1);

        $violations = $this->validator->validate($restaurant, null, [
            ValidationGroups::DRAFT,
        ]);

        self::assertSame(
            'capaciteCocktail',
            $violations->get(0)->getPropertyPath(),
        );
    }
}
