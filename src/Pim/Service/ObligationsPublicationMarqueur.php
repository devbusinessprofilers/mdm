<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Component\Form\FormView;

/**
 * Pose la var `obligatoire` (astérisque permanent du thème de l'éditeur,
 * jamais l'option `required`) sur les champs désignés par des chemins pointés
 * du formulaire de la fiche. Un groupe sans prototype ni choix étendu (liste
 * indexée, sous-formulaire) propage la mention à ses feuilles, puisque
 * l'éditeur l'aplatit dans la grille.
 */
final class ObligationsPublicationMarqueur
{
    /** @param list<string> $chemins */
    public static function marquer(FormView $view, array $chemins): void
    {
        foreach ($chemins as $chemin) {
            $cible = $view;
            foreach (explode('.', $chemin) as $segment) {
                if (!isset($cible->children[$segment])) {
                    continue 2;
                }
                $cible = $cible->children[$segment];
            }
            $cible->vars['obligatoire'] = true;
            if ([] !== $cible->children && !isset($cible->vars['prototype']) && !($cible->vars['expanded'] ?? false)) {
                foreach ($cible->children as $enfant) {
                    $enfant->vars['obligatoire'] = true;
                }
            }
        }
    }

    /**
     * Chemins réellement rendus : les pseudo-chemins (`acces.aeroport`) sont
     * ramenés à leur collection, qui porte alors la mention.
     *
     * @param list<string> $chemins
     * @param list<string> $pseudoChemins
     *
     * @return list<string>
     */
    public static function cheminsFormulaire(array $chemins, array $pseudoChemins): array
    {
        $resultat = [];
        foreach ($chemins as $chemin) {
            $resultat[] = in_array($chemin, $pseudoChemins, true) ? explode('.', $chemin, 2)[0] : $chemin;
        }

        return array_values(array_unique($resultat));
    }
}
