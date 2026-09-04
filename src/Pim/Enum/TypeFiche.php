<?php

declare(strict_types=1);

namespace App\Pim\Enum;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;

/**
 * Les gammes de fiches. Tout ce qui dépend de la gamme et n'est qu'une table
 * de correspondance (segment d'URL, libellés, classe de l'entité détail) vit
 * ici, une seule fois.
 */
enum TypeFiche: string
{
    case Lieu = 'lieu';
    case Restaurant = 'restaurant';
    case Activite = 'activite';
    case ServiceEvenementiel = 'service_evenementiel';
    case Traiteur = 'traiteur';

    /** Segment d'URL de la gamme (`/referentiel/{gamme}/…`). */
    public function slug(): string
    {
        return match ($this) {
            self::Lieu => 'lieux',
            self::Restaurant => 'restaurants',
            self::Activite => 'activites',
            self::ServiceEvenementiel => 'services',
            self::Traiteur => 'traiteurs',
        };
    }

    /**
     * Nom court de la gamme dans les noms de routes et de formulaires d'action
     * (`app_pim_lieu_submit`, `submit_lieu`, jeton `submit-lieu-{id}`).
     */
    public function domaine(): string
    {
        return match ($this) {
            self::Lieu => 'lieu',
            self::Restaurant => 'restaurant',
            self::Activite => 'activite',
            self::ServiceEvenementiel => 'service',
            self::Traiteur => 'traiteur',
        };
    }

    public static function depuisSlug(string $slug): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->slug() === $slug) {
                return $type;
            }
        }

        return null;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Lieu => 'Lieu',
            self::Restaurant => 'Restaurant',
            self::Activite => 'Activité',
            self::ServiceEvenementiel => 'Service événementiel',
            self::Traiteur => 'Plateau repas',
        };
    }

    public function libellePluriel(): string
    {
        return match ($this) {
            self::Lieu => 'Lieux',
            self::Restaurant => 'Restaurants',
            self::Activite => 'Activités',
            self::ServiceEvenementiel => 'Services',
            self::Traiteur => 'Plateaux repas',
        };
    }

    /**
     * Classe de l'entité détail (même ULID que la fiche) ; null pour une gamme
     * hors de cette version du MDM.
     *
     * @return class-string<Lieu|Restaurant|Activite|ServiceEvenementiel>|null
     */
    public function classeDetail(): ?string
    {
        return match ($this) {
            self::Lieu => Lieu::class,
            self::Restaurant => Restaurant::class,
            self::Activite => Activite::class,
            self::ServiceEvenementiel => ServiceEvenementiel::class,
            self::Traiteur => null,
        };
    }

    /** Les gammes livrées dans cette version du MDM (les plateaux repas sont hors périmètre). */
    public function estOperationnel(): bool
    {
        return self::Traiteur !== $this;
    }

    /** @return list<self> */
    public static function operationnels(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => $type->estOperationnel()));
    }
}
