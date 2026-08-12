<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Enum\DocumentUsage;
use App\Shared\Service\ParametreProviderInterface;

/**
 * Contraintes de poids des documents, surchargeables dans /admin/parametres :
 * les supports commerciaux ont leur propre plafond, tous les autres usages
 * partagent le plafond documentaire standard. Les nombres maximum par usage
 * restent portés par DocumentUsage (structurels).
 */
final readonly class DocumentContraintes
{
    public function __construct(private ParametreProviderInterface $parametres)
    {
    }

    public function maximumBytes(DocumentUsage $usage): int
    {
        $mo = $this->parametres->int(
            DocumentUsage::CommercialSupport === $usage
                ? 'dam.support_commercial_poids_max_mo'
                : 'dam.document_poids_max_mo',
        );

        return $mo * 1024 * 1024;
    }
}
