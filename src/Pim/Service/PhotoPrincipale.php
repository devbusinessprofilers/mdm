<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;

/**
 * La photo principale d'une fiche est dérivée de l'ordre : c'est la première
 * photo du tri canonique (position puis id). Point unique de vérité partagé
 * par les presenters, la politique marketplace et les processors API — il
 * n'existe plus de catégorie exclusive PHOTO_PRINCIPALE.
 */
final class PhotoPrincipale
{
    /**
     * Photos de la collection dans l'ordre canonique (position, id).
     *
     * @param iterable<RessourceLieu> $ressources
     *
     * @return list<RessourceLieu>
     */
    public static function photosTriees(iterable $ressources): array
    {
        $photos = [];
        foreach ($ressources as $ressource) {
            if (NatureRessource::Photo === $ressource->nature()) {
                $photos[] = $ressource;
            }
        }
        usort(
            $photos,
            static fn (RessourceLieu $a, RessourceLieu $b): int => [$a->position(), $a->id()]
                <=> [$b->position(), $b->id()],
        );

        return $photos;
    }

    /** @param iterable<RessourceLieu> $ressources */
    public static function principale(iterable $ressources): ?RessourceLieu
    {
        return self::photosTriees($ressources)[0] ?? null;
    }

    /**
     * Renumérote les photos 0..n-1 en plaçant la cible en tête, l'ordre
     * relatif des autres étant conservé.
     *
     * @param iterable<RessourceLieu> $ressources
     */
    public static function placerEnTete(iterable $ressources, RessourceLieu $cible): void
    {
        $photos = self::photosTriees($ressources);
        $position = 0;
        $cible->changePosition($position);
        foreach ($photos as $photo) {
            if ($photo !== $cible) {
                $photo->changePosition(++$position);
            }
        }
    }

    private function __construct()
    {
    }
}
