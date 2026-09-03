<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Enum\TypeFiche;

final class OcrCategoryPolicy
{
    /** @return array<string, DocumentUsage> */
    public function choices(TypeFiche $type): array
    {
        $common = [
            'Support commercial' => DocumentUsage::CommercialSupport,
            'Justificatif RSE' => DocumentUsage::RseEvidence,
        ];

        return match ($type) {
            TypeFiche::Lieu => ['Plan de salle' => DocumentUsage::RoomPlan, 'Plan général' => DocumentUsage::GeneralPlan, ...$common],
            TypeFiche::Restaurant => ['Plan de salle' => DocumentUsage::RoomPlan, 'Menu de restaurant' => DocumentUsage::RestaurantMenu, ...$common],
            TypeFiche::Activite, TypeFiche::ServiceEvenementiel => $common,
            TypeFiche::Traiteur => [],
        };
    }

    public function allows(TypeFiche $type, DocumentUsage $usage): bool
    {
        return in_array($usage, $this->choices($type), true);
    }

    public function label(string $usage): string
    {
        foreach (DocumentUsage::choices() as $label => $candidate) {
            if ($candidate->value === $usage) {
                return $label;
            }
        }

        return $usage;
    }
}
