<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

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
    public function suggestionsEnAttente(int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT ext.fiche_id, f.label, sug.field_path, sug.raw_value, sug.confidence, ext.created_at
             FROM ocr_suggestion sug
             INNER JOIN ocr_document_extraction ext ON ext.id = sug.extraction_id
             INNER JOIN pim_fiche f ON f.id = ext.fiche_id
             WHERE sug.status = 'pending'
             ORDER BY ext.created_at DESC
             LIMIT ".$limit,
        );

        return array_map(static fn (array $row): array => [
            'fiche_id' => (string) Ulid::fromBinary((string) $row['fiche_id']),
            'label' => null === $row['label'] ? null : (string) $row['label'],
            'field' => (string) $row['field_path'],
            'valeur' => null === $row['raw_value'] ? null : mb_strimwidth((string) $row['raw_value'], 0, 80, '…'),
            'confiance' => null === $row['confidence'] ? null : (float) $row['confidence'],
            'quand' => (string) $row['created_at'],
        ], $rows);
    }

    /**
     * Écarts relevés par la vérification BAN (score douteux ou CP/ville
     * proposés différents) : le bloc « Suggestions d'adresse » des conflits.
     * $avecProposition sépare les écarts arbitrables en un clic (la BAN
     * propose autre chose) de ceux sans résultat fiable (correction manuelle).
     *
     * @return list<array{fiche_id: string, code: int, type: string, label: ?string, adresse: string, proposition: ?string, score: ?float, quand: ?string}>
     */
    public function suggestionsAdresse(int $limit = 20, ?bool $avecProposition = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT f.id, f.code, f.type, f.label,
                loc.rue_postale, loc.code_postal, loc.ville,
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
