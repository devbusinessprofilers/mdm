<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Import\Dto\RawCsvRow;

/** Un enregistrement du CSV production : soit une ligne exploitable, soit une erreur de format. */
final readonly class LegacyCsvRecord
{
    public function __construct(
        public int $recordNumber,
        public ?RawCsvRow $row,
        public ?string $error = null,
    ) {
    }
}
