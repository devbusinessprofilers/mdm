<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Legacy\LegacyPhotoCatalog;
use App\Pim\Service\PhotoObligations;

/**
 * Publication dès l'import legacy : le drapeau « Publié / non publié » du CSV
 * reste nécessaire mais plus suffisant — les photos annoncées par photos_json
 * doivent satisfaire les obligations (minimum du type, plancher à une photo,
 * mêmes seuils que la soumission). Les photos ne sont matérialisées que par
 * app:legacy:import-photos : la décision se prend donc sur les photos
 * attendues, et app:fiches:conformite-photos rattrape en fin de pipeline les
 * fiches dont des fichiers se sont révélés absents ou invalides.
 */
final readonly class ImportPublicationPolicy
{
    public function __construct(
        private LegacyPhotoCatalog $catalog,
        private PhotoObligations $obligations,
    ) {
    }

    public function allowsPublication(TypeFiche $type, string $gamme, ?string $photosJson): bool
    {
        $entries = $this->catalog->entries((string) $photosJson, $gamme)['entries'];

        return count($entries) >= max(1, $this->obligations->minimum($type));
    }
}
