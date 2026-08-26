<?php

declare(strict_types=1);

namespace App\Pim\Service;

/** Vocabulaire des mots présents dans les noms de fiches et les villes, pour la correction orthographique. */
interface VocabulaireRechercheInterface
{
    /**
     * Mots normalisés (ASCII minuscules), groupés par longueur pour borner la
     * recherche de candidats à distance d'édition donnée.
     *
     * @return array<int, array<string, int>> longueur => (mot => fréquence)
     */
    public function motsParLongueur(): array;

    /**
     * Vocabulaire restreint aux noms de fiches contenant tous les mots donnés —
     * le contexte d'une requête départage les corrections bien mieux que la
     * seule distance d'édition.
     *
     * @param non-empty-list<string> $tokens
     *
     * @return array<string, int> mot normalisé => fréquence
     */
    public function motsAuContexte(array $tokens): array;
}
