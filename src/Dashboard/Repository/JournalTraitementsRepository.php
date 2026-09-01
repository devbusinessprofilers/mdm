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
        'enrichissement' => 'Enrichissements',
        'export' => 'Historique des exports',
    ];

    /** Libellés des sources du détail d'une demande d'enrichissement. */
    private const SOURCES_ENRICHISSEMENT = [
        'adresse' => 'Adresse',
        'sirene' => 'Sirene',
        'geoapify' => 'Geoapify',
        'datatourisme' => 'DATAtourisme',
        'wikidata' => 'Wikidata',
        'ia' => 'IA',
        'atout_france' => 'Atout France',
    ];

    /** Familles sans journal propre, visibles uniquement dans la vue des échecs. */
    public const FAMILLES_ECHECS = [
        'marketplace' => 'Diffusion marketplace',
        'outbox' => 'Outbox',
    ];

    /** Profondeur du journal : les N traitements les plus récents, toutes familles confondues. */
    public const JOURNAL_LIMIT = 1000;

    /** Lignes par page de l'écran /outils. */
    public const PAR_PAGE = 50;

    public const STATUTS_ERREUR = ['echoue', 'termine_avec_erreurs', 'failed', 'en_erreur'];

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
     *     expire?: ?string,
     * }>
     */
    public function journal(?string $famille = null, bool $seulementErreurs = false, int $limit = self::JOURNAL_LIMIT): array
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
            // Tous statuts (le journal montre aussi ce qui a abouti) ; l'index
            // IDX_ENRICHMENT_FICHE_TRANSLATION_UPDATED porte le tri (556k lignes).
            foreach ($this->connection->fetchAllAssociative(
                'SELECT t.fiche_id, t.locale, t.field_path, t.status, t.updated_at, f.label
                 FROM enrichment_fiche_translation t
                 INNER JOIN pim_fiche f ON f.id = t.fiche_id
                 ORDER BY t.updated_at DESC LIMIT '.$limit,
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
            // Tous statuts ('ready' compris) ; tri porté par IDX_DAM_MEDIA_UPDATED.
            foreach ($this->connection->fetchAllAssociative(
                'SELECT original_filename, status, error_message, updated_at
                 FROM dam_media_asset
                 WHERE deleted_at IS NULL
                 ORDER BY updated_at DESC LIMIT '.$limit,
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
        if (null === $famille || 'enrichissement' === $famille) {
            // Demandes du bouton « Enrichir ce qui manque » : en file tant que
            // le worker n'est pas passé, puis résultat par source.
            foreach ($this->connection->fetchAllAssociative(
                'SELECT r.fiche_id, r.requested_at, r.finished_at, r.resultat, f.label, f.type
                 FROM pim_fiche_enrichment_run r
                 INNER JOIN pim_fiche f ON f.id = r.fiche_id
                 ORDER BY r.requested_at DESC LIMIT '.$limit,
            ) as $row) {
                $lignes[] = [
                    'famille' => 'enrichissement',
                    'sujet' => sprintf('Enrichir ce qui manque · %s', (string) ($row['label'] ?? 'fiche')),
                    'statut' => null === $row['finished_at'] ? 'en_attente' : 'termine',
                    'erreur' => self::resumeEnrichissement($row['resultat']),
                    'quand' => (string) ($row['finished_at'] ?? $row['requested_at']),
                    'lien' => self::lienFiche((string) $row['type'], self::ulid($row['fiche_id'])),
                ];
            }
        }
        if (null === $famille || 'export' === $famille) {
            // Exports Excel du référentiel : la page de suivi (code unique)
            // reste consultable, le classeur téléchargeable jusqu'à expiration.
            foreach ($this->connection->fetchAllAssociative(
                'SELECT id, demandeur, statut, nb, erreur, requested_at, finished_at, expires_at
                 FROM pim_referentiel_export ORDER BY requested_at DESC LIMIT '.$limit,
            ) as $row) {
                $lignes[] = [
                    'famille' => 'export',
                    'sujet' => sprintf('Export Excel · %d fiche(s) · %s', (int) $row['nb'], (string) $row['demandeur']),
                    'statut' => (string) $row['statut'],
                    'erreur' => null === $row['erreur'] ? null : (string) $row['erreur'],
                    'quand' => (string) ($row['finished_at'] ?? $row['requested_at']),
                    'lien' => ['route' => 'app_mdm_referentiel_export_suivi', 'params' => ['id' => self::ulid($row['id'])]],
                    'expire' => null === $row['expires_at'] ? null : (string) $row['expires_at'],
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
        foreach ($this->connection->fetchAllAssociative(
            "SELECT id, demandeur, statut, nb, erreur,
                    COALESCE(finished_at, requested_at) AS quand
             FROM pim_referentiel_export
             WHERE statut = 'echoue'
             ORDER BY quand DESC LIMIT ".$limit,
        ) as $row) {
            $lignes[] = [
                'famille' => 'export',
                'sujet' => sprintf('Export Excel · %d fiche(s) · %s', (int) $row['nb'], (string) $row['demandeur']),
                'statut' => (string) $row['statut'],
                'erreur' => null === $row['erreur'] ? null : (string) $row['erreur'],
                'quand' => (string) $row['quand'],
                'lien' => ['route' => 'app_mdm_referentiel_export_suivi', 'params' => ['id' => self::ulid($row['id'])]],
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

    /**
     * État de toutes les files Messenger (transport Doctrine, table unique
     * `messenger_messages`), par état réel du message. Les quatre compteurs
     * partitionnent la table ; les messages traités sont supprimés, il n'y a
     * donc pas de « terminés » à compter. `available_at` est stocké en UTC par
     * le transport.
     *
     * - en_file   : en attente, pas encore pris par un worker
     * - en_cours  : réservé par un worker (livré, pas encore acquitté)
     * - planifies : en attente d'un retry (disponible dans le futur)
     * - echecs    : DLQ `failed`
     *
     * @return array{en_file: int, en_cours: int, planifies: int, echecs: int}
     */
    public function etatFilesMessenger(): array
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    COALESCE(SUM(queue_name <> 'failed' AND delivered_at IS NULL AND available_at <= UTC_TIMESTAMP()), 0) AS en_file,
                    COALESCE(SUM(queue_name <> 'failed' AND delivered_at IS NOT NULL), 0) AS en_cours,
                    COALESCE(SUM(queue_name <> 'failed' AND delivered_at IS NULL AND available_at > UTC_TIMESTAMP()), 0) AS planifies,
                    COALESCE(SUM(queue_name = 'failed'), 0) AS echecs
                 FROM messenger_messages",
            );
        } catch (\Throwable) {
            // Transport Doctrine en auto_setup=0 : la table peut manquer (base
            // neuve). L'écran Outils ne doit jamais tomber pour autant.
            return ['en_file' => 0, 'en_cours' => 0, 'planifies' => 0, 'echecs' => 0];
        }

        return [
            'en_file' => (int) ($row['en_file'] ?? 0),
            'en_cours' => (int) ($row['en_cours'] ?? 0),
            'planifies' => (int) ($row['planifies'] ?? 0),
            'echecs' => (int) ($row['echecs'] ?? 0),
        ];
    }

    /**
     * Résumé lisible du résultat par source d'une demande d'enrichissement.
     * Toutes les sources sont listées, y compris celles qui ne concernent pas
     * la gamme de la fiche (« sans objet ») : l'absence silencieuse d'une
     * source se lit sinon comme un oubli.
     */
    private static function resumeEnrichissement(mixed $resultat): ?string
    {
        $decode = is_string($resultat) ? json_decode($resultat, true) : null;
        if (!is_array($decode) || [] === $decode) {
            return null;
        }
        $parts = [];
        foreach (self::SOURCES_ENRICHISSEMENT as $source => $libelle) {
            $valeur = $decode[$source] ?? null;
            $parts[] = $libelle.' : '.match (true) {
                null === $valeur => 'sans objet pour cette gamme',
                is_int($valeur) || ctype_digit((string) $valeur) => 0 === (int) $valeur ? 'rien à proposer' : sprintf('%d suggestion%s', (int) $valeur, (int) $valeur > 1 ? 's' : ''),
                'inactif' === $valeur => 'désactivée (/admin/parametres)',
                'non_configuree' === $valeur => 'non configurée (clé API ou flux manquant)',
                'sans_adresse' === $valeur => 'code postal manquant sur la fiche',
                'indisponible' === $valeur => 'API indisponible',
                'verification_enfilee' === $valeur => 'vérification enfilée',
                default => (string) $valeur,
            };
        }
        // Une source inconnue du libellé (ajoutée après coup) reste visible.
        foreach ($decode as $source => $valeur) {
            if (!array_key_exists($source, self::SOURCES_ENRICHISSEMENT)) {
                $parts[] = $source.' : '.(is_scalar($valeur) ? (string) $valeur : '?');
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Lien vers l'éditeur de fiche, selon la gamme.
     *
     * @return array{route: string, params: array<string, string>}|null
     */
    private static function lienFiche(string $type, string $ficheId): ?array
    {
        return match ($type) {
            'lieu' => ['route' => 'app_mdm_fiche_lieu', 'params' => ['id' => $ficheId]],
            'restaurant' => ['route' => 'app_mdm_fiche_gamme', 'params' => ['gamme' => 'restaurants', 'id' => $ficheId]],
            'activite' => ['route' => 'app_mdm_fiche_gamme', 'params' => ['gamme' => 'activites', 'id' => $ficheId]],
            'service_evenementiel' => ['route' => 'app_mdm_fiche_gamme', 'params' => ['gamme' => 'services', 'id' => $ficheId]],
            default => null,
        };
    }

    private static function ulid(mixed $binaire): string
    {
        return (string) Ulid::fromBinary((string) $binaire);
    }
}
