<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\RightsValidityStatus;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use PHPUnit\Framework\TestCase;

final class AdvancedRightsTest extends TestCase
{
    public function testValidityExposesJ30AndExpiryWithoutRevokingRights(): void
    {
        $resource = new RessourceLieu();
        $resource->changeRightsExpiresAt(new \DateTimeImmutable('2100-09-01'));
        $resource->grantRights('01K1VALIDATOR0000000000000');

        self::assertSame(RightsValidityStatus::Valid, $resource->rightsValidity(new \DateTimeImmutable('2100-08-01')));
        self::assertSame(RightsValidityStatus::Expiring, $resource->rightsValidity(new \DateTimeImmutable('2100-08-05')));
        self::assertSame(RightsValidityStatus::Expired, $resource->rightsValidity(new \DateTimeImmutable('2100-09-02')));
        self::assertTrue($resource->rightsGranted(), 'Le passage de l’échéance déclenche une alerte, pas une révocation automatique.');
    }

    public function testChangingLegalMetadataRevokesAnExistingValidation(): void
    {
        $resource = new RessourceLieu();
        $resource->changeSource('Photographe A');
        $resource->grantRights('01K1VALIDATOR0000000000000');

        $resource->changeKeywords('hôtel, terrasse');
        self::assertTrue($resource->rightsGranted(), 'Les mots-clés éditoriaux ne changent pas la preuve de droits.');

        $resource->changeSource('Photographe B');
        self::assertFalse($resource->rightsGranted());
        self::assertNull($resource->rightsGrantedAt());
        self::assertNull($resource->rightsGrantedBy());
    }

    public function testAnAlreadyExpiredDateCannotBeValidated(): void
    {
        $resource = new RessourceLieu();
        $resource->changeRightsExpiresAt(new \DateTimeImmutable('2000-01-01'));

        $this->expectException(\DomainException::class);
        $resource->grantRights('01K1VALIDATOR0000000000000');
    }

    public function testExpiredRightsPreventDocumentPublication(): void
    {
        $lieu = new Lieu();
        $lieu->fiche()->publishForImport();
        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::GeneralPlan);
        $document->changeRightsExpiresAt(new \DateTimeImmutable('2000-01-01'));
        $lieu->addRessource($document);

        $this->expectException(\DomainException::class);
        $document->requestPublication();
    }
}
