<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Résolution d'un candidat (code OU libellé, quelle que soit sa forme) vers le
 * code d'une liste de valeurs : « FRUITS_DE_MER », « FRUIT DE MER » ou
 * « Fruit De Mer » aboutissent tous à l'entrée dont le libellé est
 * « Fruits de mer ». Comparaison insensible à la casse, aux accents, à la
 * ponctuation/underscores et au singulier/pluriel — mais jamais approximative :
 * un candidat non résolu retourne null (on ne devine pas).
 */
final class LovValeurResolution
{
    /**
     * @param array<string, string> $choices code → libellé (catalogue effectif : runtime d'abord)
     */
    public static function codePour(array $choices, string $candidat): ?string
    {
        $candidat = trim($candidat);
        if ('' === $candidat) {
            return null;
        }
        // Déjà un code valide de la liste.
        if (isset($choices[$candidat])) {
            return $candidat;
        }
        $cible = self::normalise($candidat);
        foreach ($choices as $code => $libelle) {
            if (self::normalise($libelle) === $cible) {
                return $code;
            }
        }
        // Tolérance singulier/pluriel : « fruit de mer » ≡ « fruits de mer ».
        $cibleSinguliere = self::singulier($cible);
        foreach ($choices as $code => $libelle) {
            if (self::singulier(self::normalise($libelle)) === $cibleSinguliere) {
                return $code;
            }
        }

        return null;
    }

    /** Minuscules, sans accents, ponctuation et underscores réduits à des espaces. */
    public static function normalise(string $valeur): string
    {
        $valeur = mb_strtolower($valeur);
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);
        if (is_string($translit)) {
            $valeur = $translit;
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $valeur));
    }

    /** Retire le « s » final de chaque mot (comparaison singulier/pluriel). */
    private static function singulier(string $normalise): string
    {
        return (string) preg_replace('/(?<=\w)s\b/', '', $normalise);
    }
}
