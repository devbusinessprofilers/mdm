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
            // Rien en strict : résolution par groupes (chaque mot OR ses
            // voisins, une seule requête) — corrige jusqu'à une faute par mot,
            // y compris plusieurs mots fautifs à la fois, avant d'abandonner
            // le dernier mot. Un mot en cours de frappe (préfixe d'un mot
            // connu) n'est jamais « corrigé ».
            [$suggestions, $correction] = $this->viaGroupes($q, dernierTokenPartiel: true);
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

    /**
     * Recherche corrigée pour une saisie soumise (mots complets), ou null.
     * Utilisée par la liste du référentiel quand la recherche stricte ne donne
     * rien : la phrase retournée retraverse le moteur fulltext normal.
     */
    public function correction(string $q): ?string
    {
        [$labels, $correction] = $this->viaGroupes($q, dernierTokenPartiel: false);

        return [] === $labels ? null : $correction;
    }

    /**
     * Résolution par groupes : un nom de fiche satisfaisant, pour chaque mot,
     * le mot lui-même ou un voisin, est une correction valide — la phrase est
     * reconstruite depuis le premier nom trouvé.
     *
     * @return array{list<string>, ?string} [labels, phrase corrigée]
     */
    private function viaGroupes(string $q, bool $dernierTokenPartiel): array
    {
        $groupes = $this->correcteur->groupes($q, $dernierTokenPartiel);
        $corrigeable = array_filter($groupes, static fn (array $g): bool => $g['candidats'] !== [$g['normalise']]);
        if ([] === $groupes || [] === $corrigeable) {
            // Aucun groupe n'apporte de candidat nouveau : la requête serait
            // identique à la recherche stricte qui vient d'échouer.
            return [[], null];
        }
        $labels = $this->repository->labelsParGroupes(
            array_map(static fn (array $g): array => $g['candidats'], $groupes),
            $q,
            self::LIMITE,
        );
        if ([] === $labels) {
            return [[], null];
        }

        // Élire la correction la plus plausible parmi les noms trouvés (moins
        // de lettres changées, préfixes préservés) et la remonter en tête.
        $meilleurIndex = 0;
        $meilleurPhrase = null;
        $meilleurScore = null;
        foreach ($labels as $index => $label) {
            $correction = $this->correcteur->correctionPourLabel($q, $groupes, $label);
            if (null === $correction['phrase']) {
                continue;
            }
            $score = [$correction['cout'], -$correction['prefixe'], $index];
            if (null === $meilleurScore || $score < $meilleurScore) {
                [$meilleurScore, $meilleurIndex, $meilleurPhrase] = [$score, $index, $correction['phrase']];
            }
        }
        if ($meilleurIndex > 0) {
            $choisi = $labels[$meilleurIndex];
            array_splice($labels, $meilleurIndex, 1);
            array_unshift($labels, $choisi);
        }

        return [$labels, $meilleurPhrase];
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
        // Les mots de tête passent aussi par les groupes : « monastere royol
        // de bru » doit corriger « royol » même quand le dernier fragment est
        // encore en cours de frappe. Le fragment reste un bonus de classement.
        $groupes = $this->correcteur->groupes(implode(' ', $tokens));
        if ([] === $groupes) {
            return $this->repository->labelsContenant($tokens, $q, self::LIMITE, $dernier);
        }

        return $this->repository->labelsParGroupes(
            array_map(static fn (array $g): array => $g['candidats'], $groupes),
            $q,
            self::LIMITE,
            $dernier,
        );
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
