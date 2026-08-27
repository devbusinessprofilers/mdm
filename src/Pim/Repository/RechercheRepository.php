<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Shared\Search\BooleanQueryFactory;
use Doctrine\DBAL\Connection;

/** Requêtes des aides à la recherche : autocomplétion et vocabulaire de correction. */
final readonly class RechercheRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Noms de fiches contenant chacun des mots, insensible casse/accents via la
     * collation. Les préfixes exacts de la saisie sortent en premier, puis les
     * noms courts (les plus proches de la saisie). $motPrefere fait remonter
     * les noms dont un mot commence par ce fragment — le dernier mot en cours
     * de frappe, quand la recherche stricte a dû l'écarter.
     *
     * @param non-empty-list<string> $tokens
     *
     * @return list<string>
     */
    public function labelsContenant(array $tokens, string $saisie, int $limite, ?string $motPrefere = null): array
    {
        return $this->labelsParGroupes(array_map(static fn (string $t): array => [$t], $tokens), $saisie, $limite, $motPrefere);
    }

    /**
     * Comme labelsContenant, mais chaque position accepte plusieurs candidats
     * (le mot tapé et/ou ses corrections possibles) : un nom qui satisfait un
     * candidat par groupe est une correction valide — la base explore ainsi
     * toutes les combinaisons en une requête, y compris deux fautes à la fois.
     *
     * L'index FULLTEXT du label porte le chemin nominal ; il ne sait faire que
     * du préfixe de mot, le repli LIKE (« berg » dans « Auberge ») n'est payé
     * que quand l'index ne trouve rien.
     *
     * @param non-empty-list<non-empty-list<string>> $groupes
     *
     * @return list<string>
     */
    public function labelsParGroupes(array $groupes, string $saisie, int $limite, ?string $motPrefere = null): array
    {
        $labels = $this->labelsRequete($groupes, $saisie, $limite, $motPrefere, fulltext: true);
        if ([] === $labels) {
            $labels = $this->labelsRequete($groupes, $saisie, $limite, $motPrefere, fulltext: false);
        }

        return $labels;
    }

    /**
     * @param non-empty-list<non-empty-list<string>> $groupes
     *
     * @return list<string>
     */
    private function labelsRequete(array $groupes, string $saisie, int $limite, ?string $motPrefere, bool $fulltext): array
    {
        $conditions = [];
        $motsEntiers = [];
        $params = ['prefixe' => addcslashes($saisie, '%_\\').'%'];
        if ($fulltext) {
            // Un groupe parenthésé par mot : au moins un candidat requis dans
            // chacun. Les candidats sortent de preg_split \p{L}\p{N} ou du
            // vocabulaire normalisé : aucun opérateur booléen à échapper.
            $conditions[] = 'MATCH (label) AGAINST (:booleen IN BOOLEAN MODE)';
            $params['booleen'] = implode(' ', array_map(
                static fn (array $candidats): string => 1 === count($candidats)
                    ? '+'.$candidats[0].'*'
                    : '+('.implode(' ', array_map(static fn (string $c): string => $c.'*', $candidats)).')',
                $groupes,
            ));
        } else {
            foreach ($groupes as $index => $candidats) {
                $groupe = [];
                foreach ($candidats as $rang => $candidat) {
                    $groupe[] = sprintf('label LIKE :t%d_%d', $index, $rang);
                    $params[sprintf('t%d_%d', $index, $rang)] = BooleanQueryFactory::likePattern($candidat);
                }
                $conditions[] = '('.implode(' OR ', $groupe).')';
            }
        }
        foreach ($groupes as $index => $candidats) {
            // Bonus de classement : un candidat du groupe matche un mot entier
            // du nom (« jeu » vaut plus dans « Jeu de Paume » que dans
            // « Jeunesse »).
            $motsEntiers[] = sprintf('(label RLIKE :mot%d)', $index);
            $params['mot'.$index] = '\\b('.implode('|', $candidats).')\\b';
        }
        $ordre = '(label LIKE :prefixe) DESC';
        if (null !== $motPrefere && '' !== $motPrefere) {
            $params['mot_debut'] = addcslashes($motPrefere, '%_\\').'%';
            $params['mot_interne'] = '% '.addcslashes($motPrefere, '%_\\').'%';
            $ordre .= ', (label LIKE :mot_debut OR label LIKE :mot_interne) DESC';
        }
        // Évalué seulement sur les lignes retenues par le WHERE : coût marginal.
        $ordre .= sprintf(', (%s) DESC', implode(' + ', $motsEntiers));

        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            sprintf(
                'SELECT DISTINCT label FROM pim_fiche
                 WHERE label IS NOT NULL AND %s
                 ORDER BY %s, CHAR_LENGTH(label) ASC, label ASC
                 LIMIT %d',
                implode(' AND ', $conditions),
                $ordre,
                max(1, $limite),
            ),
            $params,
        );
    }

    /**
     * Textes sources du vocabulaire de correction : tous les noms de fiches et
     * les villes — les champs que le placeholder de recherche promet.
     *
     * @return list<string>
     */
    public function textesVocabulaire(): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT label FROM pim_fiche WHERE label IS NOT NULL
             UNION ALL
             SELECT DISTINCT ville FROM pim_localisation WHERE ville IS NOT NULL'
        );
    }
}
