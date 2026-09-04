<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/**
 * Décline le JSON "Photos" du CSV production en entrées ordonnées prêtes à
 * l'import : catégories legacy → usages PIM selon le type de fiche (gamme),
 * positions séquentielles, plafond par fiche (invariants FichePhotoManager /
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

    /**
     * Ordre de priorité des catégories et usage PIM associé (fiches Lieu).
     * La principale est la première photo de l'ordre : master garde la tête
     * de priorité (position 0) mais porte une catégorie neutre.
     */
    private const LIEU_USAGES = [
        'master' => 'PHOTO_DIVERSE',
        'facade' => 'PHOTO_FACADE',
        'chambre' => 'PHOTO_CHAMBRE',
        'restaurant' => 'PHOTO_RESTAURATION',
        'salles_reunion' => 'PHOTO_DIVERSE',
        'divers' => 'PHOTO_DIVERSE',
    ];

    /** Les activités et services n'acceptent que PHOTO_DIVERSE. */
    private const FICHE_USAGES = [
        'master' => 'PHOTO_DIVERSE',
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
                $ordered[] = ['path' => $path, 'category' => $category, 'usage' => $usage];
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
