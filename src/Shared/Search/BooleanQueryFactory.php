<?php

declare(strict_types=1);

namespace App\Shared\Search;

/**
 * Construit la requête FULLTEXT booléenne à partir du texte tapé : chaque mot
 * devient un préfixe obligatoire (« +mot* »). Deux familles de mots sont
 * écartées parce qu'elles ne sont pas dans l'index, et les exiger éliminerait
 * toute fiche où elles apparaissent :
 * - les mots sous la taille minimale d'indexation de MariaDB
 *   (innodb_ft_min_token_size = 3) — chercher « Le Grand Pavillon Chantilly »
 *   ne trouvait pas la fiche à cause du « Le » ;
 * - les stopwords InnoDB par défaut (liste anglaise figée du serveur,
 *   innodb_ft_enable_stopword = ON) — chercher « Jeanne & The Forest » ne
 *   trouvait pas la fiche à cause du « The », jamais indexé.
 */
final class BooleanQueryFactory
{
    public const MIN_TOKEN_SIZE = 3;

    /**
     * Stopwords InnoDB par défaut d'au moins MIN_TOKEN_SIZE lettres
     * (INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD) ; les plus courts sont
     * déjà écartés par la taille.
     */
    public const STOPWORDS = [
        'about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this',
        'und', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'www',
    ];

    public static function fromText(string $text): string
    {
        return implode(' ', array_map(static fn (string $t): string => '+'.$t.'*', self::tokens($text)));
    }

    /** @return list<string> Mots de la saisie présents dans l'index (assez longs, hors stopwords), dédupliqués. */
    public static function tokens(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = false === $tokens ? [] : array_values(array_unique($tokens));

        return array_values(array_filter($tokens, static fn (string $t): bool => mb_strlen($t) >= self::MIN_TOKEN_SIZE && !in_array(mb_strtolower($t), self::STOPWORDS, true)));
    }

    /** Motif LIKE de repli quand aucun mot n'est assez long pour l'index. */
    public static function likePattern(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }
}
