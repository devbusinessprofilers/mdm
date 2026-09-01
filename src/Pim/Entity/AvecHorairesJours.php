<?php

declare(strict_types=1);

namespace App\Pim\Entity;

/**
 * Fiche portant des horaires d'ouverture par jour (Lieu, Restaurant) —
 * contrat commun de l'import/export en masse (colonnes ColumnKind::Horaire).
 */
interface AvecHorairesJours
{
    /** @return array<string, array{ouverture: ?string, fermeture: ?string}>|null */
    public function horairesJours(): ?array;

    /**
     * Écrase l'horaire d'un seul jour (une colonne d'import par jour) ;
     * `heures` null vide le jour.
     *
     * @param array{jour: string, heures: array{ouverture: ?string, fermeture: ?string}|null} $valeur
     */
    public function changeHoraireJour(array $valeur): void;
}
