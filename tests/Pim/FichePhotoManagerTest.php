<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Service\FichePhotoManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le rognage ne doit pas produire une image sous les minima d'upload
 * (dam.image_largeur_min × dam.image_hauteur_min) : la modale de recadrage
 * l'empêche côté client, le manager le garantit côté serveur.
 */
#[Group('database')]
final class FichePhotoManagerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    public function testChangeCategorieNeToucheQueLaCategorie(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(FichePhotoManager::class);

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir des catégories');
        $salle = new \App\Pim\Entity\Lieu\Salle();
        $salle->changeNom('Salle 1');
        $lieu->addSalle($salle);
        $resource = new RessourceLieu();
        $resource->changeDamAssetId('asset-categorie');
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $resource->changeLegende('Vue du parc');
        $lieu->addRessource($resource);

        $manager->changeCategorie($resource, $lieu, 'PHOTO_FACADE');
        self::assertSame('PHOTO_FACADE', $resource->usage());
        self::assertSame('Vue du parc', $resource->legende());
        self::assertNull($resource->salle());

        // Salle de réunion sans salle transmise : première salle du lieu.
        $manager->changeCategorie($resource, $lieu, 'CONFIG_PHOTO_SALLE');
        self::assertSame($salle, $resource->salle());

        // Repasser en catégorie simple détache la salle.
        $manager->changeCategorie($resource, $lieu, 'PHOTO_DIVERSE');
        self::assertNull($resource->salle());

        $this->expectException(\DomainException::class);
        $manager->changeCategorie($resource, $lieu, 'CATEGORIE_INCONNUE');
    }

    public function testUnePhotoDeSalleDeRestaurantUtiliseLeMemeCodeQueLeLieu(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(FichePhotoManager::class);

        $restaurant = new \App\Pim\Entity\Restaurant\Restaurant();
        $restaurant->changeLabel('Bistrot des catégories');
        $salle = new \App\Pim\Entity\Restaurant\RestaurantSalle();
        $salle->changeNom('Salle du fond');
        $restaurant->addSalle($salle);
        $resource = new RessourceLieu();
        $resource->changeDamAssetId('asset-restaurant');
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $restaurant->addRessource($resource);

        $manager->changeCategorie($resource, $restaurant, \App\Pim\Service\PhotoUsageCatalog::SALLE);
        self::assertSame('CONFIG_PHOTO_SALLE', $resource->usage());
        self::assertSame($salle, $resource->restaurantSalle());
        self::assertNull($resource->salle());

        $manager->changeCategorie($resource, $restaurant, 'PHOTO_DIVERSE');
        self::assertNull($resource->restaurantSalle());

        $this->expectException(\DomainException::class);
        $manager->changeCategorie($resource, $restaurant, \App\Pim\Service\PhotoUsageCatalog::SALLE, 'salle-inconnue');
    }

    public function testUnCropSousLePlancherEstRefuse(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(FichePhotoManager::class);

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir du plancher');
        $resource = new RessourceLieu();
        $resource->changeDamAssetId('asset-plancher');
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($resource);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/au moins \d+ px/');
        // Les minima admissibles vont de 100×50 px vers le haut : 10×10 est
        // toujours sous le plancher, quelle que soit la configuration.
        $manager->update($resource, $lieu, [
            'usage' => 'PHOTO_DIVERSE',
            'crop_x' => '0', 'crop_y' => '0', 'crop_width' => '10', 'crop_height' => '10',
            'rotation' => '0',
        ], 'test-actor');
    }
}
