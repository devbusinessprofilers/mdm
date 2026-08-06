<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/**
 * Décide du sort d'une traduction legacy selon la cohérence entre le français
 * actuel du PIM et le français legacy dont la traduction est issue.
 */
final class LegacyTranslationRule
{
    public const AVAILABLE = 'available';
    public const OBSOLETE = 'obsolete';
    public const SKIP = 'skip';

    /** Longueur de troncature appliquée par l'import des fiches (desc 1 000). */
    private const TRUNCATED_LENGTH = 1000;

    public static function decide(?string $pimSource, ?string $legacySource): string
    {
        $pimSource = trim((string) $pimSource);
        $legacySource = trim((string) $legacySource);
        if ('' === $pimSource) {
            // Rien à traduire côté PIM : la traduction n'aurait pas de source.
            return self::SKIP;
        }
        if ('' === $legacySource) {
            // Traduction legacy sans source française : re-validation humaine.
            return self::OBSOLETE;
        }
        if ($pimSource === $legacySource) {
            return self::AVAILABLE;
        }
        // L'import des fiches tronque certaines descriptions à 1 000 caractères
        // avec une ellipse : la traduction du texte complet reste fiable.
        if (mb_strlen($legacySource) > self::TRUNCATED_LENGTH
            && $pimSource === mb_substr($legacySource, 0, self::TRUNCATED_LENGTH - 1).'…') {
            return self::AVAILABLE;
        }

        return self::OBSOLETE;
    }
}
