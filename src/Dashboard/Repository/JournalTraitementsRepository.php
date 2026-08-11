<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

/**
 * Journal unifié des traitements de fond : imports ETL, extractions OCR,
 * traductions et médias. Chaque ligne pointe vers l'écran existant où vivent
 * le détail et les relances — le journal n'introduit aucun nouvel état.
 */
final readonly class JournalTraitementsRepository
{
    public const FAMILLES = [
        'import' => 'Imports de fiches',
        'ocr' => 'Extractions de documents',
        'traduction' => 'Traductions',
        'media' => 'Médias',
    ];

    /** Familles sans journal propre, visibles uniquement dans la vue des échecs. */
    public const FAMILLES_ECHECS = [
        'marketplace' => 'Diffusion marketplace',
        'outbox' => 'Outbox',
    ];

    private const STATUTS_ERREUR = ['echoue', 'termine_avec_erreurs', 'failed', 'en_erreur'];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{
     *     famille: string,
     *     sujet: string,
     *     statut: string,
     *     erreur: ?string,
     *     quand: string,
     *     lien: ?array{route: string, params: array<string, string>},
     * }>
     */
    public function journal(?string $famille = null, bool $seulementErreurs = false, int $limit = 50): array
    {
        $lignes = [];
        if (null === $famille || 'import' === $famille) {
            foreach ($this->connection->fetchAllAssociative(
                'SELECT id, original_filename, status, error_count, failure_message, created_at
                 FROM etl_import_job ORDER BY created_at DESC LIMIT '.$limit,
            ) as $row) {
                $lignes[] = [
                    'famille' => 'import',
                    'sujet' => (string) $row['original_filename'],
                    'statut' => (string) $row['status'],
                    'erreur' => null !== $row['failure_message']
                        ? (string) $row['failure_message']
                        : ((int) $row['error_count'] > 0 ? $row['error_count'].' ligne(s) en erreur' : null),
                    'quand' => (string) $row['created_at'],
                    'lien' => ['route' => 'app_etl_import_show', 'params' => ['id' => self::ulid($row['id'])]],
                ];
            }
        }
        if (null === $famille || 'ocr' === $famille) {
            foreach ($this->connection->fetchAllAssociative(
                'SELECT ext.id, ext.fiche_id, ext.status, ext.error_message, ext.created_at, f.label
                 FROM ocr_document_extraction ext
                 INNER JOIN pim_fiche f ON f.id = ext.fiche_id
                 ORDER BY ext.created_at DESC LIMIT '.$limit,
            ) as $row) {
                $ficheId = self::ulid($row['fiche_id']);
                $lignes[] = [
                    'famille' => 'ocr',
                    'sujet' => sprintf('Extraction · %s', (string) ($row['label'] ?? 'fiche')),
                    'statut' => (string) $row['status'],
                    'erreur' => null === $row['error_message'] ? null : (string) $row['error_message'],
                    'quand' => (string) $row['created_at'],
                    'lien' => ['route' => 'app_ocr_show', 'params' => ['id' => $ficheId, 'extractionId' => self::ulid($row['id'])]],
                ];
            }
        }
        if (null === $famille || 'traduction' === $famille) {
            foreach ($this->connection->fetchAllAssociative(
                "SELECT t.fiche_id, t.locale, t.field_path, t.status, t.updated_at, f.label
                 FROM enrichment_fiche_translation t
                 INNER JOIN pim_fiche f ON f.id = t.fiche_id
                 WHERE t.status IN ('en_erreur', 'en_cours')
                 ORDER BY t.updated_at DESC LIMIT ".$limit,
            ) as $row) {
                $lignes[] = [
                    'famille' => 'traduction',
                    'sujet' => sprintf('%s · %s (%s)', (string) ($row['label'] ?? 'fiche'), (string) $row['field_path'], (string) $row['locale']),
                    'statut' => (string) $row['status'],
                    'erreur' => 'en_erreur' === $row['status'] ? 'Traduction en échec, relance possible depuis la fiche.' : null,
                    'quand' => (string) $row['updated_at'],
                    'lien' => ['route' => 'app_enrichment_fiche_translation_show', 'params' => ['id' => self::ulid($row['fiche_id'])]],
                ];
            }
        }
        if (null === $famille || 'media' === $famille) {
            foreach ($this->connection->fetchAllAssociative(
                "SELECT original_filename, status, error_message, updated_at
                 FROM dam_media_asset
                 WHERE status IN ('failed', 'processing', 'uploaded') AND deleted_at IS NULL
                 ORDER BY updated_at DESC LIMIT ".$limit,
            ) as $row) {
                $lignes[] = [
                    'famille' => 'media',
                    'sujet' => (string) $row['original_filename'],
                    'statut' => (string) $row['status'],
                    'erreur' => null === $row['error_message'] ? null : (string) $row['error_message'],
                    'quand' => (string) $row['updated_at'],
                    'lien' => ['route' => 'app_dam_dashboard', 'params' => []],
                ];
            }
        }
        if ($seulementErreurs) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $ligne): bool => in_array($ligne['statut'], self::STATUTS_ERREUR, true),
            ));
        }
        usort($lignes, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

        return array_slice($lignes, 0, $limit);
    }

    /**
     * Traitements en échec uniquement, avec le message d'erreur réel. Les
     * critères sont strictement ceux du compteur « Traitements en échec » du
     * tableau de bord (FilesATraiterRepository::comptes()) : le filtre vit dans
     * le WHERE, un échec ancien ne peut pas être évincé par des lignes saines.
     *
     * @return list<array{
     *     famille: string,
     *     sujet: string,
     *     statut: string,
     *     erreur: ?string,
     *     quand: string,
     *     lien: ?array{route: string, params: array<string, string>},
     * }>
     */
    public function echecs(int $limit = 100): array
    {
        $lignes = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT id, original_filename, status, error_count, failure_message,
                    COALESCE(finished_at, created_at) AS quand
             FROM etl_import_job
             WHERE status IN ('echoue', 'termine_avec_erreurs')
             ORDER BY quand DESC LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'famille' => 'import',
                'sujet' => (string) $row['original_filename'],
                'statut' => (string) $row['status'],
                'erreur' => null !== $row['failure_message']
                    ? (string) $row['failure_message']
                    : ((int) $row['error_count'] > 0 ? $row['error_count'].' ligne(s) en erreur' : null),
                'quand' => (string) $row['quand'],
                'lien' => ['route' => 'app_etl_import_show', 'params' => ['id' => self::ulid($row['id'])]],
            ];
        }
        foreach ($this->connection->fetchAllAssociative(
            "SELECT ext.id, ext.fiche_id, ext.status, ext.error_message,
                    COALESCE(ext.finished_at, ext.updated_at) AS quand, f.label
             FROM ocr_document_extraction ext
             INNER JOIN pim_fiche f ON f.id = ext.fiche_id
             WHERE ext.status = 'failed'
             ORDER BY quand DESC LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'famille' => 'ocr',
                'sujet' => sprintf('Extraction · %s', (string) ($row['label'] ?? 'fiche')),
                'statut' => (string) $row['status'],
                'erreur' => null === $row['error_message'] ? null : (string) $row['error_message'],
                'quand' => (string) $row['quand'],
                'lien' => ['route' => 'app_ocr_show', 'params' => ['id' => self::ulid($row['fiche_id']), 'extractionId' => self::ulid($row['id'])]],
            ];
        }
        foreach ($this->connection->fetchAllAssociative(
            "SELECT t.fiche_id, t.locale, t.field_path, t.status, t.last_error, t.updated_at, f.label
             FROM enrichment_fiche_translation t
             INNER JOIN pim_fiche f ON f.id = t.fiche_id
             WHERE t.status = 'en_erreur'
             ORDER BY t.updated_at DESC LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'famille' => 'traduction',
                'sujet' => sprintf('%s · %s (%s)', (string) ($row['label'] ?? 'fiche'), (string) $row['field_path'], (string) $row['locale']),
                'statut' => (string) $row['status'],
                'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                'quand' => (string) $row['updated_at'],
                'lien' => ['route' => 'app_enrichment_fiche_translation_show', 'params' => ['id' => self::ulid($row['fiche_id'])]],
            ];
        }
        foreach ($this->connection->fetchAllAssociative(
            "SELECT original_filename, status, error_message, updated_at
             FROM dam_media_asset
             WHERE status = 'failed' AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'famille' => 'media',
                'sujet' => (string) $row['original_filename'],
                'statut' => (string) $row['status'],
                'erreur' => null === $row['error_message'] ? null : (string) $row['error_message'],
                'quand' => (string) $row['updated_at'],
                'lien' => ['route' => 'app_dam_dashboard', 'params' => ['filter' => 'failed']],
            ];
        }
        // Fiches dont la diffusion marketplace a épuisé ses relances : la
        // reprise se fait par `app:marketplace:sync --failed` (pas d'écran).
        foreach ($this->connection->fetchAllAssociative(
            "SELECT m.code, m.status, m.last_error, m.updated_at, f.label
             FROM etl_fiche_marketplace m
             LEFT JOIN pim_fiche f ON f.id = m.fiche_id
             WHERE m.status = 'failed'
             ORDER BY m.updated_at DESC LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'famille' => 'marketplace',
                'sujet' => sprintf('Diffusion · %s', (string) ($row['label'] ?? 'fiche '.$row['code'])),
                'statut' => (string) $row['status'],
                'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                'quand' => (string) $row['updated_at'],
                'lien' => null,
            ];
        }
        // Événements de l'outbox jamais relayés : la reprise se fait par
        // `app:outbox:failed:retry` (pas d'écran).
        foreach ($this->connection->fetchAllAssociative(
            "SELECT id, message_type, status, last_error, occurred_at
             FROM outbox_message
             WHERE status = 'failed'
             ORDER BY occurred_at DESC LIMIT ".$limit,
        ) as $row) {
            $type = (string) $row['message_type'];
            $court = false === ($pos = strrpos($type, '\\')) ? $type : substr($type, $pos + 1);
            $lignes[] = [
                'famille' => 'outbox',
                'sujet' => sprintf('%s · %s', $court, (string) $row['id']),
                'statut' => (string) $row['status'],
                'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                'quand' => (string) $row['occurred_at'],
                'lien' => null,
            ];
        }
        usort($lignes, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

        return array_slice($lignes, 0, $limit);
    }

    /** Messages de l'outbox pas encore relayés vers Messenger. */
    public function outboxEnAttente(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM outbox_message WHERE status = 'pending'",
        );
    }

    private static function ulid(mixed $binaire): string
    {
        return (string) Ulid::fromBinary((string) $binaire);
    }
}
