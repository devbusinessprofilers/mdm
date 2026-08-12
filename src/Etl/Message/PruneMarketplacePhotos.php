<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Demande la purge marketplace des photos qu'une fiche non publiée ne détient
 * plus : le snapshot éditorial publié reste servi tel quel, seules les photos
 * supprimées du PIM en sont retirées (leurs fichiers S3 sont déjà détruits).
 * La liste des photos conservées est reconstruite à l'exécution.
 */
final readonly class PruneMarketplacePhotos
{
    public function __construct(public string $ficheId)
    {
    }
}
