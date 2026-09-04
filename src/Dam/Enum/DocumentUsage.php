<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum DocumentUsage: string
{
    case RoomPlan = 'CONFIG_PLAN_SALLE';
    case GeneralPlan = 'PJ_PLAN_GENERAL';
    case CommercialSupport = 'PJ_SUPPORT_COMMERCIAUX';
    case RseEvidence = 'PJ_JUSTIFICATIF_RSE';
    case Urssaf = 'INFO_LEGALE_ATTESTATION_VIGILANCE_URSSAF';
    case LiabilityInsurance = 'INFO_LEGALE_ASSURANCE_RC_PRO';
    case BankDetails = 'MODE_PAIEMENT_RIB';
    case FactoringBankDetails = 'AFFACTURAGE_RIB';
    case Terms = 'COND_GEN_VENTE_DEPOT_DOC';
    case Convention = 'CONV_PART_FICHIER';
    case RestaurantMenu = 'MENUS';

    public function access(): DocumentAccess
    {
        return match ($this) {
            self::RoomPlan,
            self::GeneralPlan,
            self::CommercialSupport,
            self::RseEvidence,
            self::RestaurantMenu => DocumentAccess::Publishable,
            default => DocumentAccess::Private,
        };
    }

    public function maximumCount(): ?int
    {
        return match ($this) {
            self::RoomPlan, self::GeneralPlan => 2,
            self::CommercialSupport, self::RestaurantMenu => null,
            default => 1,
        };
    }

    public function requiresRoom(): bool
    {
        return self::RoomPlan === $this;
    }

    /**
     * Onglet du volet Médias où le document s'affiche : plans (« Menus »
     * pour le Restaurant), supports commerciaux, ou documents administratifs.
     */
    public function ongletMedia(): string
    {
        return match ($this) {
            self::RoomPlan, self::GeneralPlan, self::RestaurantMenu => 'plans',
            self::CommercialSupport => 'supports',
            default => 'documents',
        };
    }

    /** @return array<string, self> */
    public static function choices(): array
    {
        return [
            'Plan de salle' => self::RoomPlan,
            'Plan général' => self::GeneralPlan,
            'Support commercial' => self::CommercialSupport,
            'Justificatif RSE' => self::RseEvidence,
            'Attestation URSSAF' => self::Urssaf,
            'Assurance RC' => self::LiabilityInsurance,
            'RIB' => self::BankDetails,
            'RIB d’affacturage' => self::FactoringBankDetails,
            'Conditions générales de vente' => self::Terms,
            'Convention' => self::Convention,
            'Menu de restaurant' => self::RestaurantMenu,
        ];
    }
}
