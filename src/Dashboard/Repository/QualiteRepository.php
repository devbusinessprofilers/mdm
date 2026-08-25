<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use App\Pim\Service\ReferentielGeographiqueFrancais;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

/**
 * Données de l'écran Qualité (temps 1, sans Salesforce) : santé des données,
 * conflits à arbitrer, écarts de forme, notifications et décisions — tout est
 * dérivé de ce que le back trace déjà.
 */
final readonly class QualiteRepository
{
    private const COMPLETENESS_TABLES = [
        'lieu' => 'pim_lieu',
        'restaurant' => 'pim_restaurant',
        'activite' => 'pim_activite',
        'service_evenementiel' => 'pim_service_evenementiel',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array{type: string, fiches: int, global: ?int, marketplace: ?int, thematic: ?int, salesforce: ?int, portal: ?int, insuffisantes: int}> */
    public function santeParGamme(): array
    {
        $lignes = [];
        foreach (self::COMPLETENESS_TABLES as $type => $table) {
            /** @var array{fiches: string|int, g: string|float|null, m: string|float|null, t: string|float|null, s: string|float|null, p: string|float|null, insuffisantes: string|int|null}|false $row */
            $row = $this->connection->fetchAssociative(
                "SELECT COUNT(*) AS fiches,
                    AVG(completeness_global) AS g,
                    AVG(completeness_marketplace) AS m,
                    AVG(completeness_thematic_sites) AS t,
                    AVG(completeness_salesforce) AS s,
                    AVG(completeness_provider_portal) AS p,
                    COALESCE(SUM(completeness_global < 60), 0) AS insuffisantes
                 FROM {$table}",
            );
            if (false === $row) {
                continue;
            }
            $arrondi = static fn (string|float|null $v): ?int => null === $v ? null : (int) round((float) $v);
            $lignes[] = [
                'type' => $type,
                'fiches' => (int) $row['fiches'],
                'global' => $arrondi($row['g']),
                'marketplace' => $arrondi($row['m']),
                'thematic' => $arrondi($row['t']),
                'salesforce' => $arrondi($row['s']),
                'portal' => $arrondi($row['p']),
                'insuffisantes' => (int) $row['insuffisantes'],
            ];
        }

        return $lignes;
    }

    /** @return list<array{fiche_id: string, label: ?string, field: string, valeur: ?string, confiance: ?float, quand: string}> */
    /**
     * Pastilles du rail : le volume en attente de chaque onglet.
     *
     * @return array{conflits: int, formes: int, notifs: int, decisions: int}
     */
    public function badges(): array
    {
        $formes = $this->ecartsDeForme();

        return [
            'conflits' => (int) $this->connection->fetchOne(
                "SELECT (SELECT COUNT(*) FROM ocr_suggestion WHERE status = 'pending')
                    + (SELECT COUNT(*) FROM pim_localisation WHERE ban_ecart = 1)
                    + (SELECT COUNT(*) FROM pim_fiche_suggestion WHERE statut = 'en_attente')",
            ),
            'doublons_textes' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM pim_text_duplicate_alert WHERE status = 'pending'",
            ),
            'formes' => $formes['sans_pays'] + $formes['sans_gps'] + $formes['sans_libelle'],
            'notifs' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_relance'),
            'decisions' => (int) $this->connection->fetchOne(
                "SELECT (SELECT COUNT(*) FROM ocr_suggestion WHERE status IN ('accepted', 'rejected'))
                    + (SELECT COUNT(*) FROM audit_revision WHERE action = 'restore')",
            ),
        ];
    }

    /**
     * Champs les moins renseignés, tirés du snapshot field_fill du tableau de
     * bord — nourrit le volet droit du bloc santé de l'onglet Miroir.
     *
     * @return list<array{libelle: string, part: float, poids: string}>
     */
    public function champsFaibles(int $limit = 6): array
    {
        $payload = $this->connection->fetchOne(
            "SELECT payload FROM dashboard_snapshot WHERE kind = 'field_fill' ORDER BY computed_at DESC LIMIT 1",
        );
        if (!is_string($payload)) {
            return [];
        }
        /** @var array{perType?: array<string, array{worstFields?: list<array{code: string, label?: string, applicable?: int, filled?: int, rate?: float}>}>} $data */
        $data = (array) json_decode($payload, true);
        $faibles = [];
        foreach ($data['perType'] ?? [] as $type => $infos) {
            foreach ($infos['worstFields'] ?? [] as $field) {
                $faibles[] = [
                    'libelle' => ($field['label'] ?? $field['code']).' — '.ucfirst(str_replace('_', ' ', (string) $type)),
                    'part' => (float) ($field['rate'] ?? 0),
                    'poids' => ($field['filled'] ?? 0).'/'.($field['applicable'] ?? 0),
                ];
            }
        }
        usort($faibles, static fn (array $a, array $b): int => $a['part'] <=> $b['part']);

        return array_slice($faibles, 0, $limit);
    }

    /**
     * Tableau « Valeurs suggérées par l'IA en attente » : suggestions issues de
     * l'extraction OCR (arbitrage dans la revue d'extraction) et propositions
     * IA génériques (FicheSuggestion source « ia », arbitrage sur la fiche).
     *
     * @return list<array{fiche_id: string, label: ?string, field: string, valeur: ?string, confiance: ?float, quand: string, origine: string, type: string}>
     */
    public function suggestionsEnAttente(int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT ext.fiche_id, f.label, f.type, sug.field_path, sug.raw_value, sug.confidence, ext.created_at
             FROM ocr_suggestion sug
             INNER JOIN ocr_document_extraction ext ON ext.id = sug.extraction_id
             INNER JOIN pim_fiche f ON f.id = ext.fiche_id
             WHERE sug.status = 'pending'
             ORDER BY ext.created_at DESC
             LIMIT ".$limit,
        );
        $lignes = array_map(static fn (array $row): array => [
            'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
            'label' => null === $row['label'] ? null : (string) $row['label'],
            'field' => (string) $row['field_path'],
            'valeur' => null === $row['raw_value'] ? null : mb_strimwidth((string) $row['raw_value'], 0, 80, '…'),
            'confiance' => null === $row['confidence'] ? null : (float) $row['confidence'],
            'quand' => (string) $row['created_at'],
            'origine' => 'ocr',
            'type' => (string) $row['type'],
        ], $rows);
        foreach ($this->connection->fetchAllAssociative(
            "SELECT s.fiche_id, f.label AS fiche_label, f.type, s.label, s.valeur_proposee, s.score, s.created_at
             FROM pim_fiche_suggestion s
             INNER JOIN pim_fiche f ON f.id = s.fiche_id
             WHERE s.source = 'ia' AND s.statut = 'en_attente'
             ORDER BY s.created_at DESC
             LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
                'label' => null === $row['fiche_label'] ? null : (string) $row['fiche_label'],
                'field' => (string) $row['label'],
                'valeur' => null === $row['valeur_proposee'] ? null : mb_strimwidth((string) $row['valeur_proposee'], 0, 80, '…'),
                'confiance' => null === $row['score'] ? null : (float) $row['score'],
                'quand' => (string) $row['created_at'],
                'origine' => 'ia',
                'type' => (string) $row['type'],
            ];
        }
        usort($lignes, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

        return array_slice($lignes, 0, $limit);
    }

    /**
     * Écarts relevés par la vérification BAN (score douteux ou CP/ville
     * proposés différents) : le bloc « Suggestions d'adresse » des conflits.
     * $avecProposition sépare les écarts arbitrables en un clic (la BAN
     * propose autre chose) de ceux sans résultat fiable (correction manuelle).
     *
     * @return list<array{fiche_id: string, code: int, type: string, label: ?string, adresse: string, source: string, proposition: ?string, score: ?float, quand: ?string}>
     */
    public function suggestionsAdresse(int $limit = 20, ?bool $avecProposition = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT f.id, f.code, f.type, f.label,
                loc.rue_postale, loc.code_postal, loc.ville,
                loc.country_code, loc.pays,
                loc.ban_proposition, loc.ban_score, loc.ban_verifie_le
             FROM pim_fiche f
             INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
             WHERE loc.ban_ecart = 1'
            .match ($avecProposition) {
                true => ' AND loc.ban_proposition IS NOT NULL',
                false => ' AND loc.ban_proposition IS NULL',
                null => '',
            }.'
             ORDER BY loc.ban_score IS NULL, loc.ban_score DESC
             LIMIT '.$limit,
        );

        return array_map(static function (array $row): array {
            $proposition = null;
            if (null !== $row['ban_proposition']) {
                $decoded = json_decode((string) $row['ban_proposition'], true);
                if (is_array($decoded)) {
                    $proposition = trim(sprintf(
                        '%s %s %s',
                        $decoded['label'] ?? '',
                        $decoded['codePostal'] ?? '',
                        '' !== (string) ($decoded['label'] ?? '') ? '' : ($decoded['ville'] ?? ''),
                    ));
                    $proposition = '' === $proposition ? null : $proposition;
                }
            }

            return [
                'fiche_id' => (string) Ulid::fromBinary((string) $row['id']),
                'code' => (int) $row['code'],
                'type' => (string) $row['type'],
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'adresse' => trim(sprintf('%s, %s %s', $row['rue_postale'] ?? '—', $row['code_postal'] ?? '', $row['ville'] ?? '')),
                'source' => 'FR' === $row['country_code']
                    || (null !== $row['pays'] && 'france' === ReferentielGeographiqueFrancais::cle((string) $row['pays']))
                    ? 'BAN'
                    : 'Geoapify',
                'proposition' => $proposition,
                'score' => null === $row['ban_score'] ? null : (float) $row['ban_score'],
                'quand' => null === $row['ban_verifie_le'] ? null : (string) $row['ban_verifie_le'],
            ];
        }, $rows);
    }

    /**
     * Effectifs des deux filtres du bloc « Suggestions d'adresse ».
     *
     * @return array{avec: int, sans: int}
     */
    public function comptesSuggestionsAdresse(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(loc.ban_proposition IS NOT NULL), 0) AS avec,
                COALESCE(SUM(loc.ban_proposition IS NULL), 0) AS sans
             FROM pim_fiche f
             INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
             WHERE loc.ban_ecart = 1',
        );

        return [
            'avec' => (int) ($row['avec'] ?? 0),
            'sans' => (int) ($row['sans'] ?? 0),
        ];
    }

    /**
     * Effectifs en attente par source pour les onglets du tableau de
     * suggestions : les adresses (BAN + Geoapify géocodage réunis) et chaque
     * source d'enrichissement générique.
     *
     * @return array<string, int>
     */
    public function comptesSuggestionsParSource(): array
    {
        $adresses = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche f INNER JOIN pim_localisation loc ON loc.id = f.localisation_id WHERE loc.ban_ecart = 1',
        );
        /** @var array<string, int> $comptes */
        $comptes = ['adresses' => $adresses];
        $rows = $this->connection->fetchAllAssociative(
            "SELECT source, COUNT(*) AS n FROM pim_fiche_suggestion WHERE statut = 'en_attente' GROUP BY source",
        );
        foreach ($rows as $row) {
            $comptes[(string) $row['source']] = (int) $row['n'];
        }

        return $comptes;
    }

    /**
     * Une page du tableau de suggestions pour une source (onglet). Lignes au
     * format unifié, triées, avec le total pour la pagination.
     *
     * @return array{lignes: list<array<string, mixed>>, total: int}
     */
    public function pageSuggestions(string $source, int $page, int $taille, string $tri, string $ordre): array
    {
        $taille = max(1, min(100, $taille));
        $offset = max(0, ($page - 1) * $taille);
        $ordreSql = 'asc' === $ordre ? 'ASC' : 'DESC';

        return 'adresses' === $source
            ? $this->pageAdresses($offset, $taille, $tri, $ordreSql)
            : $this->pageGeneriques($source, $offset, $taille, $tri, $ordreSql);
    }

    /** @return array{lignes: list<array<string, mixed>>, total: int} */
    private function pageAdresses(int $offset, int $taille, string $tri, string $ordreSql): array
    {
        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche f INNER JOIN pim_localisation loc ON loc.id = f.localisation_id WHERE loc.ban_ecart = 1',
        );
        // Tri : par confiance (défaut) ou par code fiche ; les scores nuls en fin.
        $ordreBy = 'code' === $tri
            ? "f.code $ordreSql"
            : "loc.ban_score IS NULL, loc.ban_score $ordreSql";
        $rows = $this->connection->fetchAllAssociative(
            "SELECT f.id, f.code, f.type, f.label, loc.rue_postale, loc.code_postal, loc.ville,
                loc.country_code, loc.pays, loc.ban_proposition, loc.ban_score, loc.ban_verifie_le
             FROM pim_fiche f INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
             WHERE loc.ban_ecart = 1
             ORDER BY $ordreBy
             LIMIT $taille OFFSET $offset",
        );
        $lignes = array_map(function (array $row): array {
            $ficheId = (string) Ulid::fromBinary((string) $row['id']);
            $proposition = $this->propositionAdresse($row['ban_proposition']);

            return [
                'select_id' => 'adresse:'.$ficheId,
                'fiche_id' => $ficheId,
                'code' => (int) $row['code'],
                'type' => (string) $row['type'],
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'source' => 'FR' === $row['country_code'] || (null !== $row['pays'] && 'france' === ReferentielGeographiqueFrancais::cle((string) $row['pays'])) ? 'BAN' : 'Geoapify',
                'objet' => 'Adresse',
                'actuel' => trim(sprintf('%s, %s %s', $row['rue_postale'] ?? '—', $row['code_postal'] ?? '', $row['ville'] ?? '')),
                'proposition' => $proposition ?? 'Aucun résultat fiable — à corriger à la main',
                'score' => null === $row['ban_score'] ? null : (int) round((float) $row['ban_score'] * 100),
                'quand' => null === $row['ban_verifie_le'] ? null : (string) $row['ban_verifie_le'],
                'acceptable' => null !== $proposition,
            ];
        }, $rows);

        return ['lignes' => $lignes, 'total' => $total];
    }

    /** @return array{lignes: list<array<string, mixed>>, total: int} */
    private function pageGeneriques(string $source, int $offset, int $taille, string $tri, string $ordreSql): array
    {
        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM pim_fiche_suggestion WHERE statut = 'en_attente' AND source = :source",
            ['source' => $source],
        );
        $ordreBy = 'code' === $tri ? "f.code $ordreSql" : "s.score IS NULL, s.score $ordreSql, s.created_at DESC";
        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.id, s.label, s.valeur_actuelle, s.valeur_proposee, s.score, s.created_at,
                f.id AS fiche_id, f.code, f.type, f.label AS fiche_label
             FROM pim_fiche_suggestion s INNER JOIN pim_fiche f ON f.id = s.fiche_id
             WHERE s.statut = 'en_attente' AND s.source = :source
             ORDER BY $ordreBy
             LIMIT $taille OFFSET $offset",
            ['source' => $source],
        );
        $lignes = array_map(static function (array $row): array {
            return [
                'select_id' => 'suggestion:'.((string) Ulid::fromBinary((string) $row['id'])),
                'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
                'code' => (int) $row['code'],
                'type' => (string) $row['type'],
                'label' => null === $row['fiche_label'] ? null : (string) $row['fiche_label'],
                'objet' => (string) $row['label'],
                'actuel' => null === $row['valeur_actuelle'] ? '' : (string) $row['valeur_actuelle'],
                'proposition' => (string) $row['valeur_proposee'],
                'score' => null === $row['score'] ? null : (int) round((float) $row['score'] * 100),
                'quand' => null === $row['created_at'] ? null : (string) $row['created_at'],
                'acceptable' => true,
            ];
        }, $rows);

        return ['lignes' => $lignes, 'total' => $total];
    }

    /** @param mixed $banProposition JSON stocké */
    private function propositionAdresse(mixed $banProposition): ?string
    {
        if (null === $banProposition) {
            return null;
        }
        $decoded = json_decode((string) $banProposition, true);
        if (!is_array($decoded)) {
            return null;
        }
        $proposition = trim(sprintf(
            '%s %s %s',
            $decoded['label'] ?? '',
            $decoded['codePostal'] ?? '',
            '' !== (string) ($decoded['label'] ?? '') ? '' : ($decoded['ville'] ?? ''),
        ));

        return '' === $proposition ? null : $proposition;
    }

    /** @return list<array{fiches: list<array{id: string, label: ?string}>, ville: ?string}> Adresses partagées par plusieurs fiches. */
    public function doublonsAdresse(int $limit = 10): array
    {
        $empreintes = $this->connection->fetchFirstColumn(
            'SELECT loc.address_fingerprint
             FROM pim_fiche f
             INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
             WHERE loc.address_fingerprint IS NOT NULL
             GROUP BY loc.address_fingerprint
             HAVING COUNT(*) > 1
             ORDER BY COUNT(*) DESC
             LIMIT '.$limit,
        );
        $groupes = [];
        foreach ($empreintes as $empreinte) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT f.id, f.label, loc.ville
                 FROM pim_fiche f
                 INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
                 WHERE loc.address_fingerprint = :empreinte
                 ORDER BY f.updated_at DESC',
                ['empreinte' => $empreinte],
                ['empreinte' => \Doctrine\DBAL\ParameterType::BINARY],
            );
            $groupes[] = [
                'ville' => [] === $rows || null === $rows[0]['ville'] ? null : (string) $rows[0]['ville'],
                'fiches' => array_map(static fn (array $row): array => [
                    'id' => (string) Ulid::fromBinary((string) $row['id']),
                    'label' => null === $row['label'] ? null : (string) $row['label'],
                ], $rows),
            ];
        }

        return $groupes;
    }

    /**
     * Alertes de doublons de textes en attente d'arbitrage : le champ signalé
     * et sa fiche de référence, avec l'aperçu des deux textes.
     *
     * @return list<array{alert_id: string, field_label: string, kind: string, distance: ?int, signalee: array{fiche_id: string, type: string, label: ?string, snippet: ?string}, reference: array{fiche_id: string, type: string, label: ?string, snippet: ?string}}>
     */
    public function doublonsTextes(int $limit = 30): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.id AS alert_id, a.kind, a.distance, fp.field_label,
                    fp.fiche_id AS s_fiche, fp.fiche_type AS s_type, fp.snippet AS s_snippet,
                    ref.fiche_id AS r_fiche, ref.fiche_type AS r_type, ref.snippet AS r_snippet
             FROM pim_text_duplicate_alert a
             INNER JOIN pim_text_fingerprint fp ON fp.id = a.fingerprint_id
             INNER JOIN pim_text_fingerprint ref ON ref.id = a.duplicate_of_id
             WHERE a.status = :status
             ORDER BY a.created_at DESC
             LIMIT '.$limit,
            ['status' => 'pending'],
        );
        if ([] === $rows) {
            return [];
        }

        $labels = $this->ficheLabels(array_merge(
            array_column($rows, 's_fiche'),
            array_column($rows, 'r_fiche'),
        ));

        return array_map(static fn (array $row): array => [
            'alert_id' => (string) Ulid::fromBinary((string) $row['alert_id']),
            'field_label' => (string) $row['field_label'],
            'kind' => (string) $row['kind'],
            'distance' => null === $row['distance'] ? null : (int) $row['distance'],
            'signalee' => [
                'fiche_id' => (string) $row['s_fiche'],
                'type' => (string) $row['s_type'],
                'label' => $labels[(string) $row['s_fiche']] ?? null,
                'snippet' => null === $row['s_snippet'] ? null : (string) $row['s_snippet'],
            ],
            'reference' => [
                'fiche_id' => (string) $row['r_fiche'],
                'type' => (string) $row['r_type'],
                'label' => $labels[(string) $row['r_fiche']] ?? null,
                'snippet' => null === $row['r_snippet'] ? null : (string) $row['r_snippet'],
            ],
        ], $rows);
    }

    /**
     * @param list<string> $ficheIds identifiants ULID en chaîne
     *
     * @return array<string, ?string> libellé de fiche par identifiant
     */
    private function ficheLabels(array $ficheIds): array
    {
        $ficheIds = array_values(array_unique(array_filter($ficheIds)));
        if ([] === $ficheIds) {
            return [];
        }
        $binaries = array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ficheIds);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, label FROM pim_fiche WHERE id IN (:ids)',
            ['ids' => $binaries],
            ['ids' => ArrayParameterType::BINARY],
        );
        $labels = [];
        foreach ($rows as $row) {
            $labels[(string) Ulid::fromBinary((string) $row['id'])] = null === $row['label'] ? null : (string) $row['label'];
        }

        return $labels;
    }

    /** @return array{sans_pays: int, sans_gps: int, sans_libelle: int} Candidats à normalisation. */
    public function ecartsDeForme(): array
    {
        return [
            'sans_pays' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM pim_fiche f
                 INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
                 WHERE loc.country_code IS NULL',
            ),
            'sans_gps' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM pim_fiche f
                 INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
                 WHERE loc.latitude IS NULL OR loc.longitude IS NULL',
            ),
            'sans_libelle' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM pim_fiche WHERE label IS NULL',
            ),
        ];
    }

    /** @return list<array{fiche_id: string, label: ?string, quand: string, completude: int, destinataires: int}> */
    public function relances(int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.fiche_id, f.label, r.sent_at, r.completeness_at_send, r.recipient_emails
             FROM pim_fiche_relance r
             INNER JOIN pim_fiche f ON f.id = r.fiche_id
             ORDER BY r.sent_at DESC
             LIMIT '.$limit,
        );

        return array_map(static function (array $row): array {
            $destinataires = json_decode((string) $row['recipient_emails'], true);

            return [
                'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'quand' => (string) $row['sent_at'],
                'completude' => (int) $row['completeness_at_send'],
                'destinataires' => is_array($destinataires) ? count($destinataires) : 0,
            ];
        }, $rows);
    }

    /** @return list<array{fiche_id: string, label: ?string, genre: string, detail: string, acteur: string, quand: string}> Arbitrages récents. */
    public function decisions(int $limit = 20): array
    {
        $decisions = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT ext.fiche_id, f.label, sug.field_path, sug.status, sug.decided_by, sug.decided_at
             FROM ocr_suggestion sug
             INNER JOIN ocr_document_extraction ext ON ext.id = sug.extraction_id
             INNER JOIN pim_fiche f ON f.id = ext.fiche_id
             WHERE sug.status IN ('accepted', 'rejected') AND sug.decided_at IS NOT NULL
             ORDER BY sug.decided_at DESC
             LIMIT ".$limit,
        ) as $row) {
            $decisions[] = [
                'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'genre' => 'accepted' === $row['status'] ? 'Suggestion acceptée' : 'Suggestion refusée',
                'detail' => (string) $row['field_path'],
                'acteur' => (string) ($row['decided_by'] ?? '—'),
                'quand' => (string) $row['decided_at'],
            ];
        }
        foreach ($this->connection->fetchAllAssociative(
            "SELECT ar.fiche_id, f.label, ar.actor, ar.created_at
             FROM audit_revision ar
             INNER JOIN pim_fiche f ON f.id = ar.fiche_id
             WHERE ar.action = 'restore'
             ORDER BY ar.created_at DESC
             LIMIT ".$limit,
        ) as $row) {
            $decisions[] = [
                'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'genre' => 'Restauration',
                'detail' => 'Retour à une version antérieure',
                'acteur' => (string) $row['actor'],
                'quand' => (string) $row['created_at'],
            ];
        }
        usort($decisions, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

        return array_slice($decisions, 0, $limit);
    }
}
