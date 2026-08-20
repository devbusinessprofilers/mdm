<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TriReferentiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\ReadModel\ReferentielCursor;
use App\Shared\Search\BooleanQueryFactory;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

/**
 * Requêtes de la liste du référentiel : lignes filtrées, comptes de facettes,
 * voisinage et hydratations annexes. Sémantique des facettes : union à
 * l'intérieur d'un groupe, intersection entre les groupes ; le compte d'une
 * facette est calculé avec les filtres de tous les AUTRES groupes appliqués.
 */
final readonly class ReferentielRepository
{
    private const COMPLETENESS = <<<'SQL'
        CASE f.type
            WHEN 'lieu' THEN l.completeness_global
            WHEN 'activite' THEN a.completeness_global
            WHEN 'restaurant' THEN r.completeness_global
            WHEN 'service_evenementiel' THEN sv.completeness_global
            ELSE NULL
        END
        SQL;

    /**
     * Première valeur de classification « typologie » de la fiche (colonne
     * Type de la liste) : l'attribut porteur dépend de la gamme.
     */
    private const TYPOLOGIE = <<<'SQL'
        (SELECT av.label
           FROM pim_fiche_attribute_value fav
          INNER JOIN pim_attribute_value av ON av.id = fav.value_id
          INNER JOIN pim_attribute_definition ad ON ad.id = av.attribute_id
          WHERE fav.fiche_id = f.id
            AND ad.code IN ('GENERALE_TYPOLOGIE', 'TYPE_RESTAURANT', 'THEMATIQUE_ACTIVITE', 'TYPE_PRESTATAIRE')
          ORDER BY av.position ASC, av.id ASC
          LIMIT 1)
        SQL;

    private const IA_EXISTS = <<<'SQL'
        EXISTS (
            SELECT 1 FROM ocr_document_extraction ext
            INNER JOIN ocr_suggestion sug ON sug.extraction_id = ext.id
            WHERE ext.fiche_id = f.id AND sug.status = 'pending'
        )
        SQL;

    private const REPLI_EXISTS = <<<'SQL'
        EXISTS (
            SELECT 1 FROM pim_fiche_affiliation aff
            WHERE aff.fiche_id = f.id AND aff.repli = 1
        )
        SQL;

    private const MAX_PAYS = 8;
    private const MAX_VALEURS = 12;
    private const MAX_CONTRIBUTEURS = 12;

    public function __construct(private Connection $connection)
    {
    }

    public function totalReferentiel(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche');
    }

    public function count(ReferentielFiltres $filtres): int
    {
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, null);

        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM pim_fiche f %s WHERE %s', $joins, implode("\n  AND ", $conditions)),
            $params,
            $types,
        );
    }

    /** @return array<string, string> label => code, pays réellement présents dans le référentiel. */
    public function paysChoices(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT loc.country_code AS code, MAX(loc.pays) AS label, COUNT(*) AS n
             FROM pim_localisation loc
             WHERE loc.country_code IS NOT NULL
             GROUP BY loc.country_code
             ORDER BY n DESC
             LIMIT '.self::MAX_PAYS,
        );
        $choices = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $choices[('' !== (string) $row['label'] ? (string) $row['label'] : $code)] = $code;
        }

        return $choices;
    }

    /**
     * Valeurs de classification proposées en facette : les plus portées sous le
     * filtre courant, plus celles déjà cochées. Le groupe n'apparaît que si la
     * liste est réduite à une seule gamme, comme dans la maquette.
     *
     * @return array<string, int> label => id de valeur
     */
    public function valeursChoices(ReferentielFiltres $filtres): array
    {
        if (null === $filtres->gammeUnique()) {
            return [];
        }
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, 'classification');
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT fav.value_id AS id, av.label AS label, COUNT(DISTINCT f.id) AS n
                 FROM pim_fiche f
                 %s
                 INNER JOIN pim_fiche_attribute_value fav ON fav.fiche_id = f.id
                 INNER JOIN pim_attribute_value av ON av.id = fav.value_id
                 WHERE %s
                 GROUP BY fav.value_id, av.label
                 ORDER BY n DESC, av.label ASC
                 LIMIT %d',
                $joins,
                implode("\n  AND ", $conditions),
                self::MAX_VALEURS,
            ),
            $params,
            $types,
        );
        $choices = [];
        $ids = [];
        foreach ($rows as $row) {
            $choices[(string) $row['label']] = (int) $row['id'];
            $ids[(int) $row['id']] = true;
        }
        $manquantes = array_values(array_filter($filtres->valeurs, static fn (int $id): bool => !isset($ids[$id])));
        if ([] !== $manquantes) {
            $labels = $this->connection->fetchAllKeyValue(
                'SELECT id, label FROM pim_attribute_value WHERE id IN (:ids)',
                ['ids' => $manquantes],
                ['ids' => ArrayParameterType::INTEGER],
            );
            foreach ($labels as $id => $label) {
                $choices[(string) $label] = (int) $id;
            }
        }

        return $choices;
    }

    /**
     * Contributeurs proposés en facette : les utilisateurs assignés à au moins
     * une fiche, plus ceux déjà cochés dans le filtre courant.
     *
     * @return array<string, string> email => identifiant utilisateur
     */
    public function contributeursChoices(ReferentielFiltres $filtres): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT au.id, au.email, COUNT(*) AS n
             FROM account_user au
             INNER JOIN pim_fiche fa ON fa.assignee_id = au.id
             GROUP BY au.id, au.email
             ORDER BY n DESC, au.email ASC
             LIMIT '.self::MAX_CONTRIBUTEURS,
        );
        $choices = [];
        $ids = [];
        foreach ($rows as $row) {
            $choices[(string) $row['email']] = (string) $row['id'];
            $ids[(string) $row['id']] = true;
        }
        $manquants = array_values(array_filter($filtres->contributeurs, static fn (string $id): bool => !isset($ids[$id])));
        if ([] !== $manquants) {
            $emails = $this->connection->fetchAllKeyValue(
                'SELECT id, email FROM account_user WHERE id IN (:ids)',
                ['ids' => $manquants],
                ['ids' => ArrayParameterType::STRING],
            );
            foreach ($emails as $id => $email) {
                $choices[(string) $email] = (string) $id;
            }
        }

        return $choices;
    }

    /** @return list<string> Identifiants (ULID texte) de toutes les fiches du filtre, pour les actions groupées. */
    public function idsPourFiltre(ReferentielFiltres $filtres, int $plafond): array
    {
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, null, $this->specTri($filtres->tri)['jointures']);
        $rows = $this->connection->fetchFirstColumn(
            sprintf(
                'SELECT f.id FROM pim_fiche f %s WHERE %s %s LIMIT %d',
                $joins,
                implode("\n  AND ", $conditions),
                $this->ordre($filtres->tri),
                max(1, $plafond),
            ),
            $params,
            $types,
        );

        return array_map(static fn (string $id): string => (string) Ulid::fromBinary($id), $rows);
    }

    /**
     * Fiches voisines de la fiche donnée dans l'ordre de la liste filtrée,
     * pour la navigation précédente/suivante de l'édition rapide.
     *
     * @return array{precedente: ?string, suivante: ?string}
     */
    public function voisines(ReferentielFiltres $filtres, string $ficheId): array
    {
        $spec = $this->specTri($filtres->tri);
        $reference = $this->connection->fetchOne(
            sprintf(
                'SELECT %s FROM pim_fiche f %s WHERE f.id = :id',
                $spec['expr'],
                $this->jointures(array_fill_keys($spec['jointures'], true)),
            ),
            ['id' => Ulid::fromString($ficheId)->toBinary()],
            ['id' => ParameterType::BINARY],
        );
        if (false === $reference || null === $reference) {
            return ['precedente' => null, 'suivante' => null];
        }
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, null, $spec['jointures']);
        $params['ref_cle'] = ParameterType::INTEGER === $spec['type'] ? (int) $reference : (string) $reference;
        $params['ref_id'] = Ulid::fromString($ficheId)->toBinary();
        $types['ref_cle'] = $spec['type'];
        $types['ref_id'] = ParameterType::BINARY;

        $sens = $filtres->tri->direction();
        $inverse = 'ASC' === $sens ? 'DESC' : 'ASC';
        $apres = 'ASC' === $sens ? '>' : '<';
        $avant = 'ASC' === $sens ? '<' : '>';
        $keyset = static fn (string $op): string => sprintf(
            '(%1$s %2$s :ref_cle OR (%1$s = :ref_cle AND f.id %2$s :ref_id))',
            $spec['expr'],
            $op,
        );

        $suivante = $this->connection->fetchOne(
            sprintf(
                'SELECT f.id FROM pim_fiche f %s WHERE %s AND %s
                 ORDER BY %s %s, f.id %s LIMIT 1',
                $joins,
                implode("\n  AND ", $conditions),
                $keyset($apres),
                $spec['expr'],
                $sens,
                $sens,
            ),
            $params,
            $types,
        );
        $precedente = $this->connection->fetchOne(
            sprintf(
                'SELECT f.id FROM pim_fiche f %s WHERE %s AND %s
                 ORDER BY %s %s, f.id %s LIMIT 1',
                $joins,
                implode("\n  AND ", $conditions),
                $keyset($avant),
                $spec['expr'],
                $inverse,
                $inverse,
            ),
            $params,
            $types,
        );

        return [
            'precedente' => false === $precedente ? null : (string) Ulid::fromBinary($precedente),
            'suivante' => false === $suivante ? null : (string) Ulid::fromBinary($suivante),
        ];
    }

    /**
     * Page de lignes brutes, dans l'ordre du tri porté par les filtres.
     *
     * @return array{rows: list<array<string, mixed>>, hasNext: bool}
     */
    public function pageRows(ReferentielFiltres $filtres, ?ReferentielCursor $cursor, int $limit): array
    {
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, null, ['loc', 'completude', 'sd']);
        $joins .= "\nLEFT JOIN account_user au ON au.id = f.assignee_id";
        if (null !== $cursor) {
            $conditions[] = $this->conditionCursor($filtres->tri, $cursor, $params, $types);
        }
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                SELECT
                    f.id,
                    f.type,
                    f.code,
                    f.label,
                    CASE f.type
                        WHEN 'activite' THEN CASE WHEN a.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END
                        WHEN 'service_evenementiel' THEN CASE WHEN sv.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END
                        ELSE loc.ville
                    END AS ville,
                    f.status,
                    %s AS completeness,
                    COALESCE(sd.nb, 0) AS canaux,
                    au.email AS contributeur,
                    loc.pays AS pays,
                    f.business_premium AS premium,
                    %s AS typologie,
                    f.updated_at
                FROM pim_fiche f
                %s
                WHERE %s
                %s
                LIMIT %d
                SQL,
                self::COMPLETENESS,
                self::TYPOLOGIE,
                $joins,
                implode("\n  AND ", $conditions),
                $this->ordre($filtres->tri),
                $limit + 1,
            ),
            $params,
            $types,
        );

        return [
            'rows' => array_slice($rows, 0, $limit),
            'hasNext' => count($rows) > $limit,
        ];
    }

    /**
     * Lignes brutes des seules fiches demandées, dans l'ordre de la liste :
     * l'export d'une sélection cochée n'a pas à parcourir tout le résultat
     * filtré.
     *
     * @param list<string> $ids Identifiants binaires
     *
     * @return list<array<string, mixed>>
     */
    public function rowsPourIds(array $ids, TriReferentiel $tri = TriReferentiel::DEFAUT): array
    {
        if ([] === $ids) {
            return [];
        }
        // « diffusion » : sd n'est pas jointe ici, la colonne est le
        // sous-select aliasé canaux, résolu par MySQL dans le ORDER BY.
        $expr = 'diffusion' === $tri->colonne() ? 'canaux' : $this->specTri($tri)['expr'];

        return $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                SELECT
                    f.id,
                    f.type,
                    f.code,
                    f.label,
                    CASE f.type
                        WHEN 'activite' THEN CASE WHEN a.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END
                        WHEN 'service_evenementiel' THEN CASE WHEN sv.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END
                        ELSE loc.ville
                    END AS ville,
                    f.status,
                    %s AS completeness,
                    (SELECT COUNT(*) FROM pim_fiche_site_diffusion psd WHERE psd.fiche_id = f.id) AS canaux,
                    au.email AS contributeur,
                    loc.pays AS pays,
                    f.business_premium AS premium,
                    %s AS typologie,
                    f.updated_at
                FROM pim_fiche f
                LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id
                LEFT JOIN pim_lieu l ON l.fiche_id = f.id AND f.type = 'lieu'
                LEFT JOIN pim_activite a ON a.fiche_id = f.id AND f.type = 'activite'
                LEFT JOIN pim_restaurant r ON r.fiche_id = f.id AND f.type = 'restaurant'
                LEFT JOIN pim_service_evenementiel sv ON sv.fiche_id = f.id AND f.type = 'service_evenementiel'
                LEFT JOIN account_user au ON au.id = f.assignee_id
                WHERE f.id IN (:ids)
                ORDER BY %s %s, f.id %s
                SQL,
                self::COMPLETENESS,
                self::TYPOLOGIE,
                $expr,
                $tri->direction(),
                $tri->direction(),
            ),
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );
    }

    /** @return array<string, array<int|string, int>> Comptes de facettes par groupe puis par valeur. */
    public function comptes(ReferentielFiltres $filtres): array
    {
        $comptes = [];
        $comptes['gammes'] = $this->comptesParExpression($filtres, 'gamme', 'f.type');
        $comptes['statuts'] = $this->comptesParExpression($filtres, 'statut', 'f.status');
        $comptes['completudes'] = $this->comptesSommes($filtres, 'completude', [
            'complet' => self::COMPLETENESS.' >= 75',
            'publiable' => self::COMPLETENESS.' BETWEEN 60 AND 74',
            'insuffisant' => 'COALESCE('.self::COMPLETENESS.', 0) < 60',
        ], ['completude']);
        $comptes['canaux'] = $this->comptesSommes($filtres, 'canaux', [
            'c20' => 'COALESCE(sd.nb, 0) >= 20',
            'c5' => 'COALESCE(sd.nb, 0) BETWEEN 5 AND 19',
            'c1' => 'COALESCE(sd.nb, 0) BETWEEN 1 AND 4',
            'c0' => 'COALESCE(sd.nb, 0) = 0',
        ], ['sd']);
        $comptes['pays'] = $this->comptesParExpression($filtres, 'pays', 'loc.country_code', ['loc']);
        [$semaine, $jour, $sixMois] = self::bornesDates();
        $comptes['dates'] = $this->comptesSommes($filtres, 'dates', [
            'creees_semaine' => "f.created_at >= '".$semaine."'",
            'modifiees_jour' => "f.updated_at >= '".$jour."'",
            'sans_modif_6m' => "f.updated_at < '".$sixMois."'",
        ]);
        $comptes['contributeurs'] = $this->comptesParExpression($filtres, 'contributeur', 'f.assignee_id');
        $comptes['ia'] = ['1' => $this->compteAvec($filtres, 'ia', self::IA_EXISTS)];
        $comptes['repli'] = ['1' => $this->compteAvec($filtres, 'repli', self::REPLI_EXISTS)];
        $comptes['premium'] = ['1' => $this->compteAvec($filtres, 'premium', 'f.business_premium = 1')];
        $comptes['valeurs'] = $this->comptesValeurs($filtres);

        return $comptes;
    }

    /**
     * @param list<string> $ids Identifiants binaires
     *
     * @return array<string, string> id binaire => dernier acteur
     */
    public function auteurs(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $rows = $this->connection->fetchAllKeyValue(
            "SELECT ar.fiche_id, SUBSTRING_INDEX(GROUP_CONCAT(ar.actor ORDER BY ar.created_at DESC, ar.id DESC SEPARATOR '\n'), '\n', 1)
             FROM audit_revision ar
             WHERE ar.fiche_id IN (:ids)
             GROUP BY ar.fiche_id",
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );

        return array_map(strval(...), $rows);
    }

    /**
     * @param list<string> $ids Identifiants binaires
     *
     * @return array<string, string> id binaire => clé de stockage de la vignette
     */
    public function vignetteStorageKeys(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $resources = $this->connection->fetchAllAssociative(
            "SELECT rl.fiche_id, rl.dam_asset_id
             FROM pim_ressource_lieu rl
             WHERE rl.fiche_id IN (:ids) AND rl.nature = 'photo'
             ORDER BY rl.position ASC, rl.id ASC",
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );
        $premierePhoto = [];
        foreach ($resources as $resource) {
            $premierePhoto[(string) $resource['fiche_id']] ??= (string) $resource['dam_asset_id'];
        }
        if ([] === $premierePhoto) {
            return [];
        }
        $assetBinaires = [];
        foreach ($premierePhoto as $assetId) {
            try {
                $assetBinaires[$assetId] = Ulid::fromString($assetId)->toBinary();
            } catch (\InvalidArgumentException) {
            }
        }
        if ([] === $assetBinaires) {
            return [];
        }
        $renditions = $this->connection->fetchAllKeyValue(
            "SELECT r.media_id, r.storage_key FROM dam_media_rendition r
             WHERE r.name = 'small' AND r.media_id IN (:ids)",
            ['ids' => array_values($assetBinaires)],
            ['ids' => ArrayParameterType::BINARY],
        );
        $cles = [];
        foreach ($premierePhoto as $ficheId => $assetId) {
            $binaire = $assetBinaires[$assetId] ?? null;
            if (null !== $binaire && isset($renditions[$binaire])) {
                $cles[$ficheId] = (string) $renditions[$binaire];
            }
        }

        return $cles;
    }

    /**
     * @param list<string> $ids Identifiants binaires
     *
     * @return array<string, true> id binaire => suggestions IA en attente
     */
    public function marquesIa(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $rows = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT ext.fiche_id
             FROM ocr_document_extraction ext
             INNER JOIN ocr_suggestion sug ON sug.extraction_id = ext.id AND sug.status = 'pending'
             WHERE ext.fiche_id IN (:ids)",
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );

        return array_fill_keys(array_map(strval(...), $rows), true);
    }

    /**
     * @param list<string> $jointuresSelect
     *
     * @return array<string, int>
     */
    private function comptesParExpression(ReferentielFiltres $filtres, string $groupe, string $expression, array $jointuresSelect = []): array
    {
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, $groupe, $jointuresSelect);
        $rows = $this->connection->fetchAllKeyValue(
            sprintf(
                'SELECT %s AS k, COUNT(*) AS n FROM pim_fiche f %s WHERE %s AND %s IS NOT NULL GROUP BY %s',
                $expression,
                $joins,
                implode("\n  AND ", $conditions),
                $expression,
                $expression,
            ),
            $params,
            $types,
        );

        return array_map(intval(...), $rows);
    }

    /**
     * @param array<string, string> $cas
     * @param list<string>          $jointuresSelect
     *
     * @return array<string, int>
     */
    private function comptesSommes(ReferentielFiltres $filtres, string $groupe, array $cas, array $jointuresSelect = []): array
    {
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, $groupe, $jointuresSelect);
        $selects = [];
        foreach ($cas as $cle => $condition) {
            $selects[] = sprintf('COALESCE(SUM(CASE WHEN %s THEN 1 ELSE 0 END), 0) AS `%s`', $condition, $cle);
        }
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s FROM pim_fiche f %s WHERE %s', implode(', ', $selects), $joins, implode("\n  AND ", $conditions)),
            $params,
            $types,
        );

        return false === $row ? [] : array_map(intval(...), $row);
    }

    private function compteAvec(ReferentielFiltres $filtres, string $groupe, string $condition): int
    {
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, $groupe);
        $conditions[] = $condition;

        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM pim_fiche f %s WHERE %s', $joins, implode("\n  AND ", $conditions)),
            $params,
            $types,
        );
    }

    /** @return array<string, int> Comptes des valeurs de classification déjà cochées. */
    private function comptesValeurs(ReferentielFiltres $filtres): array
    {
        if (null === $filtres->gammeUnique()) {
            return [];
        }
        [$conditions, $params, $types, $joins] = $this->conditions($filtres, 'classification');
        $rows = $this->connection->fetchAllKeyValue(
            sprintf(
                'SELECT fav.value_id AS k, COUNT(DISTINCT f.id) AS n
                 FROM pim_fiche f %s
                 INNER JOIN pim_fiche_attribute_value fav ON fav.fiche_id = f.id
                 WHERE %s
                 GROUP BY fav.value_id',
                $joins,
                implode("\n  AND ", $conditions),
            ),
            $params,
            $types,
        );

        return array_map(intval(...), $rows);
    }

    /**
     * Expression SQL de la clé du tri donné, jointures qu'elle requiert dans
     * les requêtes bâties sur conditions(), et type de binding de sa valeur.
     * Les clés nullables sont COALESCE-ées pour garder un keyset comparable.
     *
     * @return array{expr: string, jointures: list<string>, type: ParameterType}
     */
    private function specTri(TriReferentiel $tri): array
    {
        return match ($tri->colonne()) {
            'nom' => ['expr' => "COALESCE(f.label, '')", 'jointures' => [], 'type' => ParameterType::STRING],
            'gamme' => ['expr' => 'f.type', 'jointures' => [], 'type' => ParameterType::STRING],
            'pays' => ['expr' => "COALESCE(loc.pays, '')", 'jointures' => ['loc'], 'type' => ParameterType::STRING],
            'statut' => ['expr' => 'f.status', 'jointures' => [], 'type' => ParameterType::STRING],
            'completude' => ['expr' => 'COALESCE('.self::COMPLETENESS.', -1)', 'jointures' => ['completude'], 'type' => ParameterType::INTEGER],
            'diffusion' => ['expr' => 'COALESCE(sd.nb, 0)', 'jointures' => ['sd'], 'type' => ParameterType::INTEGER],
            default => ['expr' => 'f.updated_at', 'jointures' => [], 'type' => ParameterType::STRING],
        };
    }

    private function ordre(TriReferentiel $tri): string
    {
        $spec = $this->specTri($tri);

        return sprintf('ORDER BY %s %s, f.id %s', $spec['expr'], $tri->direction(), $tri->direction());
    }

    /**
     * Condition keyset « après le curseur » dans le sens du tri, départagée
     * sur f.id. Forme développée : elle reste valable sur les clés calculées
     * ou COALESCE-ées, que le row constructor ne couvre pas.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     */
    private function conditionCursor(TriReferentiel $tri, ReferentielCursor $cursor, array &$params, array &$types): string
    {
        $spec = $this->specTri($tri);
        $op = 'ASC' === $tri->direction() ? '>' : '<';
        $params['cursor_cle'] = match ($tri->colonne()) {
            // Seconde près, comme la colonne — comportement historique.
            'modif' => substr($cursor->cle, 0, 19),
            'completude', 'diffusion' => (int) $cursor->cle,
            default => $cursor->cle,
        };
        $params['cursor_id'] = $cursor->id->toBinary();
        $types['cursor_cle'] = $spec['type'];
        $types['cursor_id'] = ParameterType::BINARY;
        if ('modif' === $tri->colonne()) {
            // Colonne non nulle : le row constructor exploite l'index.
            return sprintf('(f.updated_at, f.id) %s (:cursor_cle, :cursor_id)', $op);
        }

        return sprintf('(%1$s %2$s :cursor_cle OR (%1$s = :cursor_cle AND f.id %2$s :cursor_id))', $spec['expr'], $op);
    }

    /**
     * Construit jointures et conditions pour le filtre donné, en excluant au
     * besoin un groupe : le compte d'une facette se calcule sous les filtres
     * de tous les autres groupes. Seules les jointures réellement référencées
     * sont émises — par un filtre actif ou par le SELECT de l'appelant
     * ($jointuresSelect : 'loc', 'completude' et/ou 'sd') — car MySQL
     * matérialise notamment la table dérivée sd même inutilisée.
     *
     * @param list<string> $jointuresSelect
     *
     * @return array{list<string>, array<string, mixed>, array<string, mixed>, string}
     */
    private function conditions(ReferentielFiltres $filtres, ?string $groupeExclu, array $jointuresSelect = []): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        $types = [];
        $requises = array_fill_keys($jointuresSelect, true);
        if ('pays' !== $groupeExclu && [] !== $filtres->pays) {
            $requises['loc'] = true;
        }
        if ('completude' !== $groupeExclu && [] !== $filtres->completudes) {
            $requises['completude'] = true;
        }
        if ('canaux' !== $groupeExclu && [] !== $filtres->canaux) {
            $requises['sd'] = true;
        }
        $joins = $this->jointures($requises);

        $q = trim((string) $filtres->q);
        if ('' !== $q) {
            $joins .= "\nLEFT JOIN pim_fiche_search fs ON fs.fiche_id = f.id";
            $boolean = BooleanQueryFactory::fromText($q);
            $parts = [];
            if ('' !== $boolean) {
                $parts[] = 'MATCH (fs.content) AGAINST (:recherche IN BOOLEAN MODE)';
                $params['recherche'] = $boolean;
                $types['recherche'] = ParameterType::STRING;
            }
            if (ctype_digit($q) && strlen($q) <= 10 && (int) $q <= 4_294_967_295) {
                $parts[] = 'f.code = :code_exact';
                $params['code_exact'] = (int) $q;
                $types['code_exact'] = ParameterType::INTEGER;
            }
            if ([] === $parts) {
                // Aucun mot assez long pour l'index FULLTEXT : repli sur le libellé.
                $parts[] = 'f.label LIKE :recherche_libelle';
                $params['recherche_libelle'] = BooleanQueryFactory::likePattern($q);
                $types['recherche_libelle'] = ParameterType::STRING;
            }
            $conditions[] = '('.implode(' OR ', $parts).')';
        }
        if ('gamme' !== $groupeExclu && [] !== $filtres->gammes) {
            $conditions[] = 'f.type IN (:gammes)';
            $params['gammes'] = array_map(static fn (TypeFiche $t): string => $t->value, $filtres->gammes);
            $types['gammes'] = ArrayParameterType::STRING;
        }
        if ('statut' !== $groupeExclu && [] !== $filtres->statuts) {
            $conditions[] = 'f.status IN (:statuts)';
            $params['statuts'] = array_map(static fn (StatutFiche $s): string => $s->value, $filtres->statuts);
            $types['statuts'] = ArrayParameterType::STRING;
        }
        if ('completude' !== $groupeExclu && [] !== $filtres->completudes) {
            $parts = [];
            foreach ($filtres->completudes as $palier) {
                $parts[] = match ($palier) {
                    'complet' => self::COMPLETENESS.' >= 75',
                    'publiable' => self::COMPLETENESS.' BETWEEN 60 AND 74',
                    default => 'COALESCE('.self::COMPLETENESS.', 0) < 60',
                };
            }
            $conditions[] = '('.implode(' OR ', $parts).')';
        }
        if ('canaux' !== $groupeExclu && [] !== $filtres->canaux) {
            $parts = [];
            foreach ($filtres->canaux as $palier) {
                $parts[] = match ($palier) {
                    'c20' => 'COALESCE(sd.nb, 0) >= 20',
                    'c5' => 'COALESCE(sd.nb, 0) BETWEEN 5 AND 19',
                    'c1' => 'COALESCE(sd.nb, 0) BETWEEN 1 AND 4',
                    default => 'COALESCE(sd.nb, 0) = 0',
                };
            }
            $conditions[] = '('.implode(' OR ', $parts).')';
        }
        if ('pays' !== $groupeExclu && [] !== $filtres->pays) {
            $conditions[] = 'loc.country_code IN (:pays)';
            $params['pays'] = $filtres->pays;
            $types['pays'] = ArrayParameterType::STRING;
        }
        if ('dates' !== $groupeExclu && [] !== $filtres->dates) {
            [$semaine, $jour, $sixMois] = self::bornesDates();
            $parts = [];
            foreach ($filtres->dates as $palier) {
                $parts[] = match ($palier) {
                    'creees_semaine' => "f.created_at >= '".$semaine."'",
                    'modifiees_jour' => "f.updated_at >= '".$jour."'",
                    default => "f.updated_at < '".$sixMois."'",
                };
            }
            $conditions[] = '('.implode(' OR ', $parts).')';
        }
        if ('contributeur' !== $groupeExclu && [] !== $filtres->contributeurs) {
            $conditions[] = 'f.assignee_id IN (:contributeurs)';
            $params['contributeurs'] = $filtres->contributeurs;
            $types['contributeurs'] = ArrayParameterType::STRING;
        }
        if ('ia' !== $groupeExclu && $filtres->ia) {
            $conditions[] = self::IA_EXISTS;
        }
        if ('repli' !== $groupeExclu && $filtres->repli) {
            $conditions[] = self::REPLI_EXISTS;
        }
        if ('premium' !== $groupeExclu && $filtres->premium) {
            $conditions[] = 'f.business_premium = 1';
        }
        if ('classification' !== $groupeExclu && [] !== $filtres->valeurs) {
            $conditions[] = 'EXISTS (SELECT 1 FROM pim_fiche_attribute_value favf WHERE favf.fiche_id = f.id AND favf.value_id IN (:valeurs))';
            $params['valeurs'] = $filtres->valeurs;
            $types['valeurs'] = ArrayParameterType::INTEGER;
        }

        return [$conditions, $params, $types, $joins];
    }

    /** @param array<string, true> $requises */
    private function jointures(array $requises): string
    {
        $joins = '';
        if (isset($requises['loc'])) {
            $joins .= "\nLEFT JOIN pim_localisation loc ON loc.id = f.localisation_id";
        }
        if (isset($requises['completude'])) {
            $joins .= "\n".<<<'SQL'
                LEFT JOIN pim_lieu l ON l.fiche_id = f.id AND f.type = 'lieu'
                LEFT JOIN pim_activite a ON a.fiche_id = f.id AND f.type = 'activite'
                LEFT JOIN pim_restaurant r ON r.fiche_id = f.id AND f.type = 'restaurant'
                LEFT JOIN pim_service_evenementiel sv ON sv.fiche_id = f.id AND f.type = 'service_evenementiel'
                SQL;
        }
        if (isset($requises['sd'])) {
            $joins .= "\nLEFT JOIN (SELECT fiche_id, COUNT(*) AS nb FROM pim_fiche_site_diffusion GROUP BY fiche_id) sd ON sd.fiche_id = f.id";
        }

        return $joins;
    }

    /** @return array{string, string, string} Bornes UTC : début de semaine, début de jour (Europe/Paris) et il y a six mois. */
    private static function bornesDates(): array
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $utc = new \DateTimeZone('UTC');
        $format = static fn (\DateTimeImmutable $d): string => $d->setTimezone($utc)->format('Y-m-d H:i:s');

        return [
            $format(new \DateTimeImmutable('monday this week 00:00', $paris)),
            $format(new \DateTimeImmutable('today 00:00', $paris)),
            $format(new \DateTimeImmutable('-6 months', $paris)),
        ];
    }
}
