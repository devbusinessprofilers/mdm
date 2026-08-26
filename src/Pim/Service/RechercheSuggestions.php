<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Repository\RechercheRepository;
use App\Shared\Search\BooleanQueryFactory;

/**
 * Suggestions de noms de fiches pour l'autocomplétion des champs de recherche.
 * Match mot à mot (un LIKE par mot) : « jeu paume » comme « jeu de la paume »
 * trouvent « Auberge du Jeu de Paume ». Quand la saisie stricte ne suffit pas,
 * deux replis : les mots déjà complets sans le dernier mot en cours de frappe,
 * puis le correcteur orthographique.
 */
final readonly class RechercheSuggestions
{
    private const LIMITE = 10;

    public function __construct(
        private RechercheRepository $repository,
        private RechercheCorrecteur $correcteur,
    ) {
    }

    /** @return array{suggestions: list<string>, correction: ?string} */
    public function pour(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['suggestions' => [], 'correction' => null];
        }

        $suggestions = $this->labels($q);
        $correction = null;
        if ([] === $suggestions) {
            // Rien en strict : sonder les candidates de correction jusqu'à la
            // première qui trouve (« pavilon » → « pavillon ») — avant
            // d'abandonner le dernier mot, sinon une faute en fin de saisie ne
            // serait jamais corrigée. Le reste de la requête départage les
            // voisins ; un mot en cours de frappe (préfixe d'un mot connu)
            // n'est jamais « corrigé ».
            foreach ($this->correcteur->corrections($q, dernierTokenPartiel: true) as $corrigee) {
                $trouvees = $this->labels($corrigee);
                if ([] !== $trouvees) {
                    [$suggestions, $correction] = [$trouvees, $corrigee];
                    break;
                }
            }
        }
        if ([] === $suggestions) {
            // L'utilisateur est sans doute au milieu du dernier mot (« auberge
            // du jeu de pom ») : chercher sur les mots déjà complets, en
            // remontant les noms dont un mot commence par le fragment tapé.
            $suggestions = $this->labelsSansDernierMot($q);
        }
        if ([] !== $suggestions && null === $correction && count($suggestions) < self::LIMITE) {
            // Des résultats mais de la place : compléter avec la candidate la
            // plus probable seulement, pour ne pas noyer les vrais matchs.
            $corrections = $this->correcteur->corrections($q, dernierTokenPartiel: true);
            $complement = [] === $corrections ? [] : array_diff($this->labels($corrections[0]), $suggestions);
            if ([] !== $complement) {
                $suggestions = array_slice([...$suggestions, ...array_values($complement)], 0, self::LIMITE);
                $correction = $corrections[0];
            }
        }

        return ['suggestions' => $suggestions, 'correction' => $correction];
    }

    /** @return list<string> */
    private function labels(string $q): array
    {
        $tokens = $this->tokensRetenus($q);
        if ([] === $tokens) {
            return [];
        }

        return $this->repository->labelsContenant($tokens, $q, self::LIMITE);
    }

    /** @return list<string> */
    private function labelsSansDernierMot(string $q): array
    {
        $tokens = $this->tokensRetenus($q);
        $dernier = array_pop($tokens);
        if (null === $dernier || [] === $tokens) {
            return [];
        }

        return $this->repository->labelsContenant($tokens, $q, self::LIMITE, $dernier);
    }

    /**
     * Mots de la saisie soumis au LIKE. Les mots courts (« du », « la ») sont
     * trop peu discriminants pour un LIKE chacun — sauf s'ils sont toute la
     * saisie.
     *
     * @return list<string>
     */
    private function tokensRetenus(string $q): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique($tokens));
        $longs = array_values(array_filter($tokens, static fn (string $t): bool => mb_strlen($t) >= BooleanQueryFactory::MIN_TOKEN_SIZE));

        return [] === $longs ? $tokens : $longs;
    }
}
