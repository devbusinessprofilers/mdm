<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/**
 * Décline le JSON "Photos" du CSV production en entrées ordonnées prêtes à
 * l'import : catégories legacy → usages PIM selon le type de fiche (gamme),
 * positions séquentielles, plafond par fiche (invariants LieuPhotoManager /
 * ValidActivite) — le surplus est retourné séparément (skipped_limit).
 */
final class LegacyPhotoCatalog
{
    public const MAX_PHOTOS_LIEU = 25;
    public const MAX_PHOTOS_FICHE = 10;

    public const GAMMES_LIEU = ['Hôtel', 'Lieu', 'Centre de congrès'];
    public const GAMME_ACTIVITE = 'Idée';
    public const GAMME_SERVICE = 'Prestataires de service';
    public const GAMME_RESTAURANT = 'Restaurant';

    /** Ordre de priorité des catégories et usage PIM associé (fiches Lieu). */
    private const LIEU_USAGES = [
        'master' => 'PHOTO_PRINCIPALE',
        'facade' => 'PHOTO_FACADE',
        'chambre' => 'PHOTO_CHAMBRE',
        'restaurant' => 'PHOTO_RESTAURATION',
        'salles_reunion' => 'PHOTO_DIVERSE',
        'divers' => 'PHOTO_DIVERSE',
    ];

    /** Les activités et services n'acceptent que PHOTO_PRINCIPALE / PHOTO_DIVERSE. */
    private const FICHE_USAGES = [
        'master' => 'PHOTO_PRINCIPALE',
        'facade' => 'PHOTO_DIVERSE',
        'chambre' => 'PHOTO_DIVERSE',
        'restaurant' => 'PHOTO_DIVERSE',
        'salles_reunion' => 'PHOTO_DIVERSE',
        'divers' => 'PHOTO_DIVERSE',
    ];

    /**
     * @return array{
     *     entries: list<array{path: string, category: string, usage: string, position: int}>,
     *     skipped: list<array{path: string, category: string, usage: string, position: int}>,
     * }
     */
    public function entries(string $photosJson, string $gamme): array
    {
        $isFicheBased = in_array($gamme, [self::GAMME_ACTIVITE, self::GAMME_SERVICE, self::GAMME_RESTAURANT], true);
        $usages = $isFicheBased ? self::FICHE_USAGES : self::LIEU_USAGES;
        $maxPhotos = $isFicheBased ? self::MAX_PHOTOS_FICHE : self::MAX_PHOTOS_LIEU;

        $decoded = json_decode($photosJson, true);
        if (!is_array($decoded)) {
            return ['entries' => [], 'skipped' => []];
        }
        $ordered = [];
        $seen = [];
        $isFirstMaster = true;
        foreach ($usages as $category => $usage) {
            $paths = $decoded[$category] ?? [];
            if (!is_array($paths)) {
                continue;
            }
            foreach ($paths as $path) {
                if (!is_string($path) || '' === trim($path)) {
                    continue;
                }
                $path = trim($path);
                if (isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                if ('master' === $category) {
                    // Une seule PHOTO_PRINCIPALE autorisée par fiche.
                    $ordered[] = ['path' => $path, 'category' => $category, 'usage' => $isFirstMaster ? 'PHOTO_PRINCIPALE' : 'PHOTO_DIVERSE'];
                    $isFirstMaster = false;
                } else {
                    $ordered[] = ['path' => $path, 'category' => $category, 'usage' => $usage];
                }
            }
        }
        $entries = [];
        $skipped = [];
        foreach ($ordered as $position => $entry) {
            $entry['position'] = $position;
            if ($position < $maxPhotos) {
                $entries[] = $entry;
            } else {
                $skipped[] = $entry;
            }
        }

        return ['entries' => $entries, 'skipped' => $skipped];
    }
}
