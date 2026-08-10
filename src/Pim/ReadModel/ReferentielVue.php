<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

/** Résultat complet d'une consultation de la liste du référentiel. */
final readonly class ReferentielVue
{
    /**
     * @param list<ReferentielLigne>                 $lignes
     * @param array<string, array<int|string, int>>  $comptes        Comptes de facettes par groupe puis par valeur
     * @param array<string, string>                  $paysChoices    label => code pays, pour le formulaire de filtres
     * @param array<string, int>                     $valeursChoices label => id de valeur de classification
     * @param array<string, string>                  $contributeursChoices email => identifiant utilisateur
     */
    public function __construct(
        public array $lignes,
        public ?string $nextCursor,
        public int $total,
        public int $totalReferentiel,
        public array $comptes,
        public array $paysChoices,
        public array $valeursChoices,
        public array $contributeursChoices,
    ) {
    }
}
