<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\StatutFiche;
use PHPUnit\Framework\TestCase;

/**
 * Liaison 1–1 Lieu ↔ Restaurant : synchronisation des deux côtés, garde
 * d'unicité, suivi des fiches liées à resynchroniser, et absence de
 * transition de workflow sur la fiche liée (mise à jour technique).
 */
final class RestaurantLieuLiaisonTest extends TestCase
{
    public function testChangeLieuSynchroniseLesDeuxCotes(): void
    {
        $lieu = new Lieu();
        $restaurant = new Restaurant();

        $restaurant->changeLieu($lieu);

        self::assertSame($lieu, $restaurant->lieu());
        self::assertSame($restaurant, $lieu->restaurant());
    }

    public function testChangeRestaurantSynchroniseLesDeuxCotes(): void
    {
        $lieu = new Lieu();
        $restaurant = new Restaurant();

        $lieu->changeRestaurant($restaurant);

        self::assertSame($restaurant, $lieu->restaurant());
        self::assertSame($lieu, $restaurant->lieu());
    }

    public function testChangerDeLieuDetacheLAncien(): void
    {
        $ancien = new Lieu();
        $nouveau = new Lieu();
        $restaurant = new Restaurant();
        $restaurant->changeLieu($ancien);

        $restaurant->changeLieu($nouveau);

        self::assertNull($ancien->restaurant());
        self::assertSame($restaurant, $nouveau->restaurant());
        self::assertSame($nouveau, $restaurant->lieu());
    }

    public function testDetacherLaLiaison(): void
    {
        $lieu = new Lieu();
        $restaurant = new Restaurant();
        $restaurant->changeLieu($lieu);

        $lieu->changeRestaurant(null);

        self::assertNull($lieu->restaurant());
        self::assertNull($restaurant->lieu());
    }

    public function testLieuDejaPrisRefuse(): void
    {
        $lieu = new Lieu();
        $autre = new Restaurant();
        $autre->changeLieu($lieu);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('déjà associé à un autre restaurant');
        (new Restaurant())->changeLieu($lieu);
    }

    public function testRestaurantDejaPrisRefuse(): void
    {
        $restaurant = new Restaurant();
        $autre = new Lieu();
        $autre->changeRestaurant($restaurant);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('déjà associé à un autre lieu');
        (new Lieu())->changeRestaurant($restaurant);
    }

    public function testDrainRetourneLesFichesTouchesPuisSeVide(): void
    {
        $ancien = new Lieu();
        $nouveau = new Lieu();
        $restaurant = new Restaurant();
        $restaurant->changeLieu($ancien);
        $restaurant->drainFichesLieesAResynchroniser();

        $restaurant->changeLieu($nouveau);

        $fiches = $restaurant->drainFichesLieesAResynchroniser();
        self::assertEqualsCanonicalizing(
            [$ancien->fiche(), $nouveau->fiche()],
            $fiches,
        );
        self::assertSame([], $restaurant->drainFichesLieesAResynchroniser());
    }

    public function testLaFicheLieePublieeResteSansTransitionDeWorkflow(): void
    {
        $lieu = new Lieu();
        $lieu->fiche()->submitForValidation('acteur');
        $lieu->fiche()->validate('validateur');
        $lieu->fiche()->publish();

        $restaurant = new Restaurant();
        $restaurant->changeLieu($lieu);

        // La liaison est une mise à jour technique côté lieu : la fiche reste
        // publiée (un markChanged l'aurait repassée « en cours »).
        self::assertSame(StatutFiche::Publiee, $lieu->fiche()->status());
        // Le restaurant, lui, est la fiche éditée : modification métier.
        self::assertSame(StatutFiche::EnCours, $restaurant->fiche()->status());
    }
}
