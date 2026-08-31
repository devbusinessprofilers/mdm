<?php

declare(strict_types=1);

namespace App\Shared\Search;

/**
 * Construit la requête FULLTEXT booléenne à partir du texte tapé : chaque mot
 * devient un préfixe obligatoire (« +mot* »). Les mots sous la taille minimale
 * d'indexation de MariaDB (innodb_ft_min_token_size = 3) sont écartés : ils ne
 * sont pas dans l'index, et les exiger éliminerait toute fiche où ils
 * n'apparaissent qu'en version courte — chercher « Le Grand Pavillon
 * Chantilly » ne trouvait pas la fiche à cause du « Le ».
 */
final class BooleanQueryFactory
{
    public const MIN_TOKEN_SIZE = 3;

    public static function fromText(string $text): string
    {
        return implode(' ', array_map(static fn (string $t): string => '+'.$t.'*', self::tokens($text)));
    }

    /** @return list<string> Mots de la saisie assez longs pour l'index, dédupliqués. */
    public static function tokens(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = false === $tokens ? [] : array_values(array_unique($tokens));

        return array_values(array_filter($tokens, static fn (string $t): bool => mb_strlen($t) >= self::MIN_TOKEN_SIZE));
    }

    /** Motif LIKE de repli quand aucun mot n'est assez long pour l'index. */
    public static function likePattern(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }
}
