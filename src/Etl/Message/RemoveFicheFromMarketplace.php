<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Demande la dépublication de la fiche sur la marketplace (is_published=false
 * côté marketplace, jamais de suppression physique).
 */
final readonly class RemoveFicheFromMarketplace
{
    public function __construct(public string $ficheId)
    {
    }
}
