<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Service\PhotoPrincipale;
use PHPUnit\Framework\TestCase;

/**
 * La photo principale est dérivée de l'ordre : première photo du tri
 * canonique (position, id) — il n'existe plus de catégorie exclusive.
 */
final class PhotoPrincipaleTest extends TestCase
{
    public function testTriCanoniqueParPositionPuisId(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Manoir du tri');
        $b = $this->photo($lieu, 'asset-b', position: 2);
        $a = $this->photo($lieu, 'asset-a', position: 0);
        $c = $this->photo($lieu, 'asset-c', position: 1);
        $document = new RessourceLieu();
        $document->changeDamAssetId('asset-doc');
        $document->changeNature(NatureRessource::Document);
        $document->changeUsage('PJ_SUPPORT_COMMERCIAUX');
        $document->changePosition(0);
        $lieu->addRessource($document);

        $tri = PhotoPrincipale::photosTriees($lieu->ressources());

        self::assertSame([$a, $c, $b], $tri);
        self::assertSame($a, PhotoPrincipale::principale($lieu->ressources()));
    }

    public function testSansPhotoLaPrincipaleEstNulle(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Manoir vide');

        self::assertNull(PhotoPrincipale::principale($lieu->ressources()));
        self::assertSame([], PhotoPrincipale::photosTriees($lieu->ressources()));
    }

    public function testPlacerEnTeteRenumeroteEnConservantLOrdreRelatif(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Manoir des positions');
        $a = $this->photo($lieu, 'asset-a', position: 0);
        $b = $this->photo($lieu, 'asset-b', position: 1);
        $c = $this->photo($lieu, 'asset-c', position: 2);

        PhotoPrincipale::placerEnTete($lieu->ressources(), $c);

        self::assertSame(0, $c->position());
        self::assertSame(1, $a->position());
        self::assertSame(2, $b->position());
        self::assertSame($c, PhotoPrincipale::principale($lieu->ressources()));
    }

    private function photo(Lieu $lieu, string $assetId, int $position): RessourceLieu
    {
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($assetId);
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $resource->changePosition($position);
        $lieu->addRessource($resource);

        return $resource;
    }
}
