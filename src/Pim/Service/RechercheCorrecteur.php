<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Shared\Search\BooleanQueryFactory;

/**
 * Correction orthographique d'une requête de recherche, contre le vocabulaire
 * des mots réellement cherchables (noms de fiches et villes). Deux familles de
 * candidates, ordonnées de la plus probable à la moins probable :
 *
 *  1. les mots absents du vocabulaire remplacés par leur voisin le plus proche
 *     (Levenshtein) — la faute franche ;
 *  2. les substitutions d'un seul mot, même connu, par un voisin — la faute qui
 *     retombe par hasard sur un mot existant (« pomme » est dans « Hôtel La
 *     Pomme », mais la fiche cherchée dit « paume »).
 *
 * L'appelant sonde les candidates dans l'ordre et garde la première qui donne
 * des résultats : le reste de la requête départage les voisins. N'est appelé
 * que sur le chemin « 0 résultat » — le coût n'affecte jamais une recherche qui
 * aboutit.
 */
final readonly class RechercheCorrecteur
{
    /** Voisins retenus par mot — au-delà, le sondage par l'appelant coûterait plus qu'il ne rapporte. */
    private const MAX_CANDIDATS_PAR_MOT = 4;

    /** Plafond de candidates retournées, donc de requêtes sondées par l'appelant. */
    private const MAX_REQUETES = 12;

    /**
     * Voisins par groupe de candidats — bien plus large que le sondage phrase
     * par phrase : la base explore toutes les combinaisons en une requête, un
     * candidat de plus n'ajoute qu'un LIKE. Assez grand pour qu'un voisin à
     * distance 2 (« paume » depuis « pomme ») survive à la foule des voisins
     * à distance 1 (somme, homme, comme…).
     */
    private const MAX_CANDIDATS_PAR_GROUPE = 16;

    public function __construct(private VocabulaireRechercheInterface $vocabulaire)
    {
    }

    /**
     * Requêtes corrigées candidates, déterministes à saisie égale (condition de
     * survie de la pagination keyset, qui les recalcule à chaque page).
     *
     * @param bool $dernierTokenPartiel en autocomplétion, le dernier mot est un
     *                                  préfixe en cours de frappe : il n'est
     *                                  corrigé que s'il ne commence aucun mot
     *                                  connu
     *
     * @return list<string>
     */
    public function corrections(string $q, bool $dernierTokenPartiel = false): array
    {
        $q = trim($q);
        if ('' === $q || ctype_digit($q)) {
            return [];
        }
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $tokens) {
            return [];
        }
        $mots = $this->vocabulaire->motsParLongueur();
        if ([] === $mots) {
            return [];
        }

        $dernier = array_key_last($tokens);
        $eligibles = [];
        $base = [];
        $baseModifiee = false;
        foreach ($tokens as $index => $token) {
            $base[$index] = $token;
            $normalise = $this->normalise($token);
            if (strlen($normalise) < BooleanQueryFactory::MIN_TOKEN_SIZE) {
                continue;
            }
            $connu = isset($mots[strlen($normalise)][$normalise]);
            if (!$connu && $dernierTokenPartiel && $index === $dernier && $this->prefixeConnu($mots, $normalise)) {
                continue;
            }
            $eligibles[$index] = $normalise;
            if (!$connu) {
                $candidats = $this->candidats($mots, $normalise);
                if ([] !== $candidats) {
                    // Mot franc de faute : la meilleure candidate intègre
                    // d'office son voisin le plus proche.
                    $base[$index] = $candidats[0]['mot'];
                    $baseModifiee = true;
                }
            }
        }

        $requetes = $baseModifiee ? [implode(' ', $base)] : [];

        // Substitutions d'un seul mot sur la base, sans jamais en cumuler deux
        // (l'explosion combinatoire ne vaut pas son coût). Deux étages : les
        // voisins qui co-occurrent avec le reste de la requête dans un nom de
        // fiche (le contexte départage — « auberge jeu » désigne « paume »
        // parmi les nombreux voisins de « pomme »), puis les voisins globaux.
        $substitutions = [];
        foreach ($eligibles as $index => $normalise) {
            $autres = [];
            foreach (array_keys($eligibles) as $autre) {
                if ($autre !== $index) {
                    $autres[] = $this->normalise($base[$autre]);
                }
            }
            $exclu = $this->normalise($base[$index]);
            $contextuels = [] === $autres ? [] : $this->candidatsAuContexte($this->vocabulaire->motsAuContexte($autres), $normalise, $exclu);
            $globaux = $this->candidats($mots, $normalise);
            foreach ([[0, $contextuels], [1, $globaux]] as [$etage, $candidats]) {
                foreach ($candidats as $candidat) {
                    if ($candidat['mot'] === $exclu) {
                        continue;
                    }
                    $variante = $base;
                    $variante[$index] = $candidat['mot'];
                    $substitutions[] = ['q' => implode(' ', $variante), 'etage' => $etage, 'distance' => $candidat['distance'], 'frequence' => $candidat['frequence']];
                }
            }
        }
        usort($substitutions, static fn (array $a, array $b): int => [$a['etage'], $a['distance'], -$a['frequence'], $a['q']] <=> [$b['etage'], $b['distance'], -$b['frequence'], $b['q']]);
        foreach ($substitutions as $substitution) {
            $requetes[] = $substitution['q'];
        }

        $saisieNormalisee = $this->normalisePhrase($q);
        $vues = [];
        $retenues = [];
        foreach ($requetes as $requete) {
            $cle = $this->normalisePhrase($requete);
            if ($cle === $saisieNormalisee || isset($vues[$cle])) {
                continue;
            }
            $vues[$cle] = true;
            $retenues[] = $requete;
            if (count($retenues) >= self::MAX_REQUETES) {
                break;
            }
        }

        return $retenues;
    }

    /**
     * Voisins d'un mot dans le vocabulaire, hors le mot lui-même, du plus
     * proche au plus fréquent puis alphabétique — un ordre total, pour un
     * résultat identique à chaque appel.
     *
     * @param array<int, array<string, int>> $mots
     *
     * @return list<array{mot: string, distance: int, frequence: int}>
     */
    private function candidats(array $mots, string $normalise, int $max = self::MAX_CANDIDATS_PAR_MOT): array
    {
        $longueur = strlen($normalise);
        $seuil = $longueur <= 4 ? 1 : 2;
        $candidats = [];
        for ($taille = $longueur - $seuil; $taille <= $longueur + $seuil; ++$taille) {
            foreach ($mots[$taille] ?? [] as $mot => $frequence) {
                $mot = (string) $mot;
                if ($mot === $normalise) {
                    continue;
                }
                $distance = levenshtein($normalise, $mot);
                if ($distance <= $seuil) {
                    $candidats[] = ['mot' => $mot, 'distance' => $distance, 'frequence' => $frequence];
                }
            }
        }
        usort($candidats, static fn (array $a, array $b): int => [$a['distance'], -$a['frequence'], $a['mot']] <=> [$b['distance'], -$b['frequence'], $b['mot']]);

        return array_slice($candidats, 0, $max);
    }

    /**
     * Candidats par mot de la saisie, pour une résolution en une requête par
     * groupes OR (labelsParGroupes) : chaque mot retenu (≥ 3 lettres) devient
     * un groupe « lui-même s'il est connu + ses voisins » — deux fautes dans
     * deux mots différents se résolvent ainsi ensemble, là où le sondage
     * phrase par phrase s'engageait sur un premier voisin possiblement faux.
     *
     * @param bool $dernierTokenPartiel le dernier mot en cours de frappe
     *                                  (préfixe d'un mot connu) reste seul
     *                                  dans son groupe : jamais « corrigé »
     *
     * @return list<array{token: string, normalise: string, candidats: non-empty-list<string>}>
     */
    public function groupes(string $q, bool $dernierTokenPartiel = false): array
    {
        $q = trim($q);
        if ('' === $q || ctype_digit($q)) {
            return [];
        }
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mots = $this->vocabulaire->motsParLongueur();
        if ([] === $tokens || [] === $mots) {
            return [];
        }

        $dernier = array_key_last($tokens);
        $groupes = [];
        foreach ($tokens as $index => $token) {
            $normalise = $this->normalise($token);
            if (strlen($normalise) < BooleanQueryFactory::MIN_TOKEN_SIZE) {
                continue;
            }
            $connu = isset($mots[strlen($normalise)][$normalise]);
            $candidats = [$normalise];
            $garde = !$connu && $dernierTokenPartiel && $index === $dernier && $this->prefixeConnu($mots, $normalise);
            if (!$garde) {
                foreach ($this->candidats($mots, $normalise, self::MAX_CANDIDATS_PAR_GROUPE) as $candidat) {
                    $candidats[] = $candidat['mot'];
                }
                if (!$connu) {
                    // Le mot fautif lui-même ne matchera rien d'utile : seuls
                    // ses voisins comptent — sauf s'il n'en a aucun.
                    $candidats = count($candidats) > 1 ? array_slice($candidats, 1) : $candidats;
                }
            }
            $groupes[] = ['token' => $token, 'normalise' => $normalise, 'candidats' => array_values(array_unique($candidats))];
        }

        return $groupes;
    }

    /**
     * Correction constatée sur un nom trouvé par groupes : chaque mot de la
     * saisie remplacé par celui de ses candidats présent dans le nom — aucun
     * remplacement si le mot tapé y figure déjà. Le coût (distance totale) et
     * la longueur des préfixes communs départagent les noms candidats : une
     * faute de frappe épargne rarement les premières lettres.
     *
     * @param list<array{token: string, normalise: string, candidats: non-empty-list<string>}> $groupes
     *
     * @return array{phrase: ?string, cout: int, prefixe: int}
     */
    public function correctionPourLabel(string $q, array $groupes, string $label): array
    {
        $motsLabel = array_flip(preg_split('/[^a-z0-9]+/', $this->normalisePhrase($label), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $remplacements = [];
        $cout = 0;
        $prefixe = 0;
        foreach ($groupes as $groupe) {
            $normalise = $groupe['normalise'];
            if (isset($motsLabel[$normalise])) {
                continue;
            }
            foreach ($groupe['candidats'] as $candidat) {
                if ($candidat !== $normalise && isset($motsLabel[$candidat])) {
                    $remplacements[$normalise] = $candidat;
                    $cout += levenshtein($normalise, $candidat);
                    $commun = 0;
                    $max = min(strlen($normalise), strlen($candidat));
                    while ($commun < $max && $normalise[$commun] === $candidat[$commun]) {
                        ++$commun;
                    }
                    $prefixe += $commun;
                    break;
                }
            }
        }
        if ([] === $remplacements) {
            return ['phrase' => null, 'cout' => 0, 'prefixe' => 0];
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $phrase = array_map(fn (string $token): string => $remplacements[$this->normalise($token)] ?? $token, $tokens);

        return ['phrase' => implode(' ', $phrase), 'cout' => $cout, 'prefixe' => $prefixe];
    }

    /**
     * Voisins pris dans un vocabulaire contextuel plat (mots co-occurrents
     * dans les noms de fiches) — mêmes seuils et même ordre que candidats().
     *
     * @param array<string, int> $contexte mot => fréquence
     *
     * @return list<array{mot: string, distance: int, frequence: int}>
     */
    private function candidatsAuContexte(array $contexte, string $normalise, string $exclu): array
    {
        $longueur = strlen($normalise);
        $seuil = $longueur <= 4 ? 1 : 2;
        $candidats = [];
        foreach ($contexte as $mot => $frequence) {
            $mot = (string) $mot;
            if ($mot === $exclu || abs(strlen($mot) - $longueur) > $seuil) {
                continue;
            }
            $distance = levenshtein($normalise, $mot);
            if ($distance <= $seuil) {
                $candidats[] = ['mot' => $mot, 'distance' => $distance, 'frequence' => $frequence];
            }
        }
        usort($candidats, static fn (array $a, array $b): int => [$a['distance'], -$a['frequence'], $a['mot']] <=> [$b['distance'], -$b['frequence'], $b['mot']]);

        return array_slice($candidats, 0, self::MAX_CANDIDATS_PAR_MOT);
    }

    /** @param array<int, array<string, int>> $mots */
    private function prefixeConnu(array $mots, string $normalise): bool
    {
        $longueur = strlen($normalise);
        foreach ($mots as $taille => $bucket) {
            if ($taille <= $longueur) {
                continue;
            }
            foreach ($bucket as $mot => $frequence) {
                if (str_starts_with((string) $mot, $normalise)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalise(string $token): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $token);
        $token = mb_strtolower(false === $ascii ? $token : $ascii);

        return preg_replace('/[^a-z0-9]+/', '', $token) ?? $token;
    }

    private function normalisePhrase(string $phrase): string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map($this->normalise(...), $tokens));
    }
}
