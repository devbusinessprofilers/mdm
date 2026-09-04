<?php

declare(strict_types=1);

namespace App\Audit;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\FicheAttributValeur;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantPeriodeFermeture;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceAcces;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;

/**
 * Chemin d'un champ dans l'audit d'une fiche (`nom`, `lieu.descGenerale`,
 * `salles[id].nom`, `medias[id].legende`…). Ces chemins sont persistés dans
 * `audit_change` : encodage (DoctrineAuditSubscriber), décodage
 * (RestorableFieldCatalog) et reconstruction (FusionChampsCatalogue) doivent
 * suivre la même convention, définie ici une seule fois.
 */
final class AuditPath
{
    /**
     * Préfixes des chemins restaurables par setter : entité de gamme (même nom
     * court que TypeFiche::domaine()) et sous-entités singulières.
     *
     * @var array<string, class-string>
     */
    public const PREFIXES = [
        'fiche' => Fiche::class,
        'lieu' => Lieu::class,
        'activite' => Activite::class,
        'service' => ServiceEvenementiel::class,
        'restaurant' => Restaurant::class,
        'localisation' => Localisation::class,
        'administratif' => FicheAdministratif::class,
        'tarification' => LieuTarification::class,
    ];

    /** Chemin d'un champ d'une entité auditée. */
    public static function pour(object $entity, string $field): string
    {
        return match (true) {
            $entity instanceof Fiche => match ($field) {
                'label' => 'nom',
                'status' => 'workflow.status',
                'siteSelections' => 'sitesDiffusion',
                default => 'fiche.'.$field,
            },
            $entity instanceof Lieu => 'lieu.'.$field,
            $entity instanceof Activite => 'activite.'.$field,
            $entity instanceof ServiceEvenementiel => 'service.'.$field,
            $entity instanceof Restaurant => 'restaurant.'.$field,
            $entity instanceof OffreActivite => sprintf('%s[%s].%s', $entity->type()->value.'s', $entity->id(), $field),
            $entity instanceof Localisation => 'localisation.'.$field,
            $entity instanceof FicheAdministratif => 'administratif.'.$field,
            $entity instanceof LieuTarification => 'tarification.'.$field,
            $entity instanceof Salle, $entity instanceof RestaurantSalle => sprintf('salles[%s].%s', $entity->id(), $field),
            $entity instanceof PeriodeFermeture, $entity instanceof RestaurantPeriodeFermeture => sprintf('fermetures[%s].%s', $entity->id(), $field),
            $entity instanceof AccesLieu, $entity instanceof RestaurantAcces, $entity instanceof ServiceAcces => sprintf('acces[%s].%s', $entity->id(), $field),
            $entity instanceof RessourceLieu => sprintf('%s[%s].%s', NatureRessource::Document === $entity->nature() ? 'documents' : 'medias', $entity->id(), $field),
            $entity instanceof FicheAttributValeur => sprintf('attributs[%s].%s', $entity->attributeCode(), $field),
            default => $field,
        };
    }

    /**
     * Décode un chemin restaurable en [préfixe, champ] ; null pour les chemins
     * à crochets (collections, médias, LOV), le statut workflow et les préfixes
     * inconnus. `nom` est le libellé de la fiche.
     *
     * @return array{string, string}|null
     */
    public static function decoder(string $path): ?array
    {
        if ('nom' === $path) {
            return ['fiche', 'label'];
        }
        if ('workflow.status' === $path || str_contains($path, '[') || 1 !== substr_count($path, '.')) {
            return null;
        }
        [$prefix, $field] = explode('.', $path, 2);
        if (!isset(self::PREFIXES[$prefix]) || '' === $field || !preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $field)) {
            return null;
        }

        return [$prefix, $field];
    }
}
