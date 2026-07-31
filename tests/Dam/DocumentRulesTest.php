<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Enum\DocumentAccess;
use App\Dam\Enum\DocumentPublicationStatus;
use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use PHPUnit\Framework\TestCase;

final class DocumentRulesTest extends TestCase
{
    public function testUsageRulesMatchTheContract(): void
    {
        self::assertSame(
            10 * 1024 * 1024,
            DocumentUsage::RoomPlan->maximumBytes(),
        );
        self::assertSame(
            100 * 1024 * 1024,
            DocumentUsage::CommercialSupport->maximumBytes(),
        );
        self::assertSame(2, DocumentUsage::RoomPlan->maximumCount());
        self::assertNull(DocumentUsage::CommercialSupport->maximumCount());
        self::assertSame(
            10 * 1024 * 1024,
            DocumentUsage::RestaurantMenu->maximumBytes(),
        );
        self::assertNull(DocumentUsage::RestaurantMenu->maximumCount());
        self::assertSame(
            DocumentAccess::Publishable,
            DocumentUsage::RestaurantMenu->access(),
        );
        self::assertSame(
            DocumentAccess::Private,
            DocumentUsage::Urssaf->access(),
        );
        self::assertSame(
            DocumentAccess::Publishable,
            DocumentUsage::RseEvidence->access(),
        );
    }

    public function testPublishableDocumentRequiresRightsAndPublishedLieu(): void
    {
        $lieu = new Lieu();
        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::GeneralPlan);
        $lieu->addRessource($document);
        $this->expectException(\DomainException::class);
        $document->requestPublication();
    }

    public function testPrivateDocumentCanNeverBecomePublic(): void
    {
        $lieu = new Lieu();
        $lieu->fiche()->publishForImport();
        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::Urssaf);
        $document->grantRights('validator');
        $lieu->addRessource($document);
        try {
            $document->requestPublication();
            self::fail('Publication should have failed.');
        } catch (\DomainException) {
        }
        self::assertSame(
            DocumentPublicationStatus::Private,
            $document->publicationStatus(),
        );
        self::assertNull($document->publicStorageKey());
    }
}
