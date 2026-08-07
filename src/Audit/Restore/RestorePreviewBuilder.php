<?php

declare(strict_types=1);

namespace App\Audit\Restore;

use App\Audit\Entity\AuditRevision;
use App\Audit\ValueNormalizer;
use App\Pim\Entity\Fiche;

final readonly class RestorePreviewBuilder
{
    public function __construct(
        private RestorableFieldCatalog $catalog,
        private ValueNormalizer $normalizer,
    ) {
    }

    /**
     * Statut de chaque change de la révision : restaurable, identique
     * (valeur d'époque = valeur actuelle), ou exclu (collection, média,
     * LOV, workflow, champ disparu).
     *
     * @return list<array{path: string, old_value: mixed, current_value: mixed, status: string}>
     */
    public function preview(AuditRevision $revision, Fiche $fiche): array
    {
        $rows = [];
        foreach ($revision->changes() as $change) {
            $path = $change->path();
            if (!$this->catalog->isRestorable($path)) {
                $rows[] = [
                    'path' => $path,
                    'old_value' => $change->oldValue(),
                    'current_value' => null,
                    'status' => 'exclu',
                ];
                continue;
            }
            try {
                $current = $this->normalizer->normalize(
                    $this->catalog->currentValue($fiche, $path),
                );
            } catch (NotRestorableException) {
                $rows[] = [
                    'path' => $path,
                    'old_value' => $change->oldValue(),
                    'current_value' => null,
                    'status' => 'exclu',
                ];
                continue;
            }
            $rows[] = [
                'path' => $path,
                'old_value' => $change->oldValue(),
                'current_value' => $current,
                'status' => $current === $change->oldValue()
                    ? 'identique'
                    : 'restaurable',
            ];
        }

        return $rows;
    }

    /** @param list<array{status: string}> $rows */
    public static function restorableCount(array $rows): int
    {
        return count(array_filter(
            $rows,
            static fn (array $row): bool => 'restaurable' === $row['status'],
        ));
    }
}
