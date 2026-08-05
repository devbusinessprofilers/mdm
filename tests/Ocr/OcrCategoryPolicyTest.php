<?php

declare(strict_types=1);

namespace App\Tests\Ocr;

use App\Dam\Enum\DocumentUsage;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;

final class OcrCategoryPolicyTest extends TestCase
{
    public function testOnlyExpectedNonSensitiveCategoriesAreAvailableForEachFiche(): void
    {
        $policy = new OcrCategoryPolicy();

        self::assertSame(
            [DocumentUsage::RoomPlan, DocumentUsage::GeneralPlan, DocumentUsage::CommercialSupport, DocumentUsage::RseEvidence],
            array_values($policy->choices(TypeFiche::Lieu)),
        );
        self::assertSame(
            [DocumentUsage::RoomPlan, DocumentUsage::RestaurantMenu, DocumentUsage::CommercialSupport, DocumentUsage::RseEvidence],
            array_values($policy->choices(TypeFiche::Restaurant)),
        );
        foreach ([TypeFiche::Activite, TypeFiche::ServiceEvenementiel] as $type) {
            self::assertSame([DocumentUsage::CommercialSupport, DocumentUsage::RseEvidence], array_values($policy->choices($type)));
        }

        foreach ([DocumentUsage::Urssaf, DocumentUsage::LiabilityInsurance, DocumentUsage::BankDetails, DocumentUsage::FactoringBankDetails, DocumentUsage::Terms, DocumentUsage::Convention] as $sensitive) {
            foreach ([TypeFiche::Lieu, TypeFiche::Restaurant, TypeFiche::Activite, TypeFiche::ServiceEvenementiel] as $type) {
                self::assertFalse($policy->allows($type, $sensitive));
            }
        }
        self::assertSame('Menu de restaurant', $policy->label(DocumentUsage::RestaurantMenu->value));
    }
}
