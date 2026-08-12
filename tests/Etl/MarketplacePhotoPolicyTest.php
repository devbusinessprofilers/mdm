<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\MarketplacePhotoPolicy;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Service\PhotoObligations;
use App\Tests\Support\ParametresFixes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class MarketplacePhotoPolicyTest extends TestCase
{
    private MarketplacePhotoPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new MarketplacePhotoPolicy(new PhotoObligations(new ParametresFixes([
            'photos.min_lieu' => '4',
            'photos.max_lieu' => '25',
            'photos.min_autres' => '1',
            'photos.max_autres' => '10',
        ])));
    }

    public function testMinimumIsFourForLieuAndOneElsewhere(): void
    {
        self::assertSame(4, $this->policy->minimumFor(TypeFiche::Lieu));
        self::assertSame(1, $this->policy->minimumFor(TypeFiche::Restaurant));
        self::assertSame(1, $this->policy->minimumFor(TypeFiche::Activite));
        self::assertSame(1, $this->policy->minimumFor(TypeFiche::ServiceEvenementiel));
    }

    public function testLegacyFicheWithoutPimPhotosStaysPublishable(): void
    {
        $fiche = $this->lieuFiche(photos: 0);

        self::assertFalse($this->policy->appliesTo($fiche));
        self::assertFalse($this->policy->satisfiedByResources($fiche));
        self::assertTrue($this->policy->allowsPublication($fiche));
    }

    public function testLieuWithFourPhotosAndPrincipaleIsSatisfied(): void
    {
        $fiche = $this->lieuFiche(photos: 4, withPrincipale: true);

        self::assertTrue($this->policy->appliesTo($fiche));
        self::assertTrue($this->policy->satisfiedByResources($fiche));
        self::assertTrue($this->policy->allowsPublication($fiche));
    }

    public function testLieuBelowMinimumIsNotPublishable(): void
    {
        $fiche = $this->lieuFiche(photos: 3, withPrincipale: true);

        self::assertTrue($this->policy->appliesTo($fiche));
        self::assertFalse($this->policy->allowsPublication($fiche));
    }

    public function testLieuWithoutPrincipaleIsNotPublishable(): void
    {
        $fiche = $this->lieuFiche(photos: 4, withPrincipale: false);

        self::assertFalse($this->policy->allowsPublication($fiche));
    }

    public function testDocumentsDoNotCountAsPhotos(): void
    {
        $fiche = $this->lieuFiche(photos: 0);
        $document = new RessourceLieu();
        $document->changeDamAssetId((string) new Ulid());
        $document->changeNature(NatureRessource::Document);
        $document->changeUsage('BROCHURE');
        $fiche->addResource($document);

        self::assertFalse($this->policy->appliesTo($fiche));
        self::assertTrue($this->policy->allowsPublication($fiche));
    }

    public function testRemoteStateFollowsTheSameThresholds(): void
    {
        self::assertTrue($this->policy->satisfiedByRemote(TypeFiche::Lieu, 4, true));
        self::assertFalse($this->policy->satisfiedByRemote(TypeFiche::Lieu, 3, true));
        self::assertFalse($this->policy->satisfiedByRemote(TypeFiche::Lieu, 4, false));
        self::assertTrue($this->policy->satisfiedByRemote(TypeFiche::Restaurant, 1, true));
        self::assertFalse($this->policy->satisfiedByRemote(TypeFiche::Restaurant, 0, false));
    }

    private function lieuFiche(int $photos, bool $withPrincipale = true): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $fiche = $lieu->fiche();
        for ($i = 0; $i < $photos; ++$i) {
            $resource = new RessourceLieu();
            $resource->changeDamAssetId((string) new Ulid());
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeUsage(0 === $i && $withPrincipale ? 'PHOTO_PRINCIPALE' : 'PHOTO_DIVERSE');
            $resource->changePosition($i + 1);
            $fiche->addResource($resource);
        }

        return $fiche;
    }
}
