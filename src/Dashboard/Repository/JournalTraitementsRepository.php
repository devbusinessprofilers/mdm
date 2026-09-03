<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use App\Dashboard\Journal\EtatTraitement;
use App\Dashboard\Journal\FamilleTraitement;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

/**
 * Journal unifié des traitements de fond : imports ETL, extractions OCR,
 * traductions, médias, enrichissements, exports, visibilité géographique,
 * diffusion marketplace, Salesforce et outbox. Chaque ligne pointe vers
 * l'écran existant où vivent le détail et les relances — le journal
 * n'introduit aucun nouvel état : chaque famille garde sa table de suivi,
 * définie une seule fois dans familles(), d'où dérivent le journal complet,
 * la vue des échecs et le compteur « Traitements en échec ».
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
        'visibilite' => 'Visibilité géographique',
        'marketplace' => 'Diffusion marketplace',
        'salesforce' => 'Synchronisation Salesforce',
    ];

    /** Familles sans journal propre, visibles uniquement parmi les échecs. */
    public const FAMILLES_ECHECS = [
        'outbox' => 'Outbox',
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

    /** Profondeur du journal : les N traitements les plus récents, toutes familles confondues. */
    public const JOURNAL_LIMIT = 1000;

    /** Lignes par page de l'écran /outils. */
    public const PAR_PAGE = 50;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{
     *     famille: string,
     *     sujet: string,
     *     statut: string,
     *     etat: EtatTraitement,
     *     erreur: ?string,
     *     quand: string,
     *     lien: ?array{route: string, params: array<string, string>},
     *     expire?: ?string,
     * }>
     */
    public function journal(?string $famille = null, bool $seulementErreurs = false, int $limit = self::JOURNAL_LIMIT): array
    {
        $lignes = [];
        foreach ($this->familles() as $definition) {
            if (null !== $famille && $definition->code !== $famille) {
                continue;
            }
            if (!$definition->dansJournal && !$seulementErreurs) {
                continue;
            }
            if ($seulementErreurs && null === $definition->echec) {
                continue;
            }
            foreach ($this->connection->fetchAllAssociative($definition->requete($seulementErreurs, $limit)) as $row) {
                $ligne = ($definition->ligne)($row);
                $lignes[] = ['famille' => $definition->code, 'etat' => EtatTraitement::depuisStatut($ligne['statut'])] + $ligne;
            }
        }
        usort($lignes, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

        return array_slice($lignes, 0, $limit);
    }

    /**
     * Traitements en échec uniquement, avec le message d'erreur réel : mêmes
     * critères que compterEchecs().
     *
     * @return list<array{
     *     famille: string,
     *     sujet: string,
     *     statut: string,
     *     etat: EtatTraitement,
     *     erreur: ?string,
     *     quand: string,
     *     lien: ?array{route: string, params: array<string, string>},
     *     expire?: ?string,
     * }>
     */
    public function echecs(int $limit = 100): array
    {
        return $this->journal(null, true, $limit);
    }

    /** Nombre total de traitements en échec, toutes familles confondues (ligne du tableau de bord). */
    public function compterEchecs(): int
    {
        $total = 0;
        foreach ($this->familles() as $definition) {
            if (null !== $definition->echec) {
                $total += (int) $this->connection->fetchOne($definition->requeteCompteEchecs());
            }
        }

        return $total;
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
     * La définition de chaque famille : table de suivi, condition d'échec,
     * tri, et présentation d'une ligne. Les index qui portent les tris :
     * IDX_ENRICHMENT_FICHE_TRANSLATION_UPDATED (556k lignes),
     * IDX_DAM_MEDIA_UPDATED, IDX_ETL_FICHE_MARKETPLACE_UPDATED.
     *
     * @return list<FamilleTraitement>
     */
    private function familles(): array
    {
        return [
            new FamilleTraitement(
                code: 'import',
                colonnes: 'id, original_filename, status, error_count, failure_message, COALESCE(finished_at, created_at) AS quand',
                depuis: 'etl_import_job',
                condition: null,
                echec: "status IN ('echoue', 'termine_avec_erreurs')",
                tri: 'quand',
                ligne: static fn (array $row): array => [
                    'sujet' => (string) $row['original_filename'],
                    'statut' => (string) $row['status'],
                    'erreur' => null !== $row['failure_message']
                        ? (string) $row['failure_message']
                        : ((int) $row['error_count'] > 0 ? $row['error_count'].' ligne(s) en erreur' : null),
                    'quand' => (string) $row['quand'],
                    'lien' => ['route' => 'app_etl_import_show', 'params' => ['id' => self::ulid($row['id'])]],
                ],
            ),
            new FamilleTraitement(
                code: 'ocr',
                colonnes: 'ext.id, ext.fiche_id, ext.status, ext.error_message, COALESCE(ext.finished_at, ext.updated_at) AS quand, f.label',
                depuis: 'ocr_document_extraction ext INNER JOIN pim_fiche f ON f.id = ext.fiche_id',
                condition: null,
                echec: "ext.status = 'failed'",
                tri: 'quand',
                ligne: static fn (array $row): array => [
                    'sujet' => sprintf('Extraction · %s', (string) ($row['label'] ?? 'fiche')),
                    'statut' => (string) $row['status'],
                    'erreur' => null === $row['error_message'] ? null : (string) $row['error_message'],
                    'quand' => (string) $row['quand'],
                    'lien' => ['route' => 'app_ocr_show', 'params' => ['id' => self::ulid($row['fiche_id']), 'extractionId' => self::ulid($row['id'])]],
                ],
            ),
            new FamilleTraitement(
                code: 'traduction',
                colonnes: 't.fiche_id, t.locale, t.field_path, t.status, t.last_error, t.updated_at, f.label',
                depuis: 'enrichment_fiche_translation t INNER JOIN pim_fiche f ON f.id = t.fiche_id',
                condition: null,
                echec: "t.status = 'en_erreur'",
                tri: 't.updated_at',
                ligne: static fn (array $row): array => [
                    'sujet' => sprintf('%s · %s (%s)', (string) ($row['label'] ?? 'fiche'), (string) $row['field_path'], (string) $row['locale']),
                    'statut' => (string) $row['status'],
                    'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                    'quand' => (string) $row['updated_at'],
                    'lien' => ['route' => 'app_enrichment_fiche_translation_show', 'params' => ['id' => self::ulid($row['fiche_id'])]],
                ],
            ),
            new FamilleTraitement(
                code: 'media',
                colonnes: 'original_filename, status, error_message, updated_at',
                depuis: 'dam_media_asset',
                condition: 'deleted_at IS NULL',
                echec: "status = 'failed'",
                tri: 'updated_at',
                ligne: static fn (array $row): array => [
                    'sujet' => (string) $row['original_filename'],
                    'statut' => (string) $row['status'],
                    'erreur' => null === $row['error_message'] ? null : (string) $row['error_message'],
                    'quand' => (string) $row['updated_at'],
                    'lien' => ['route' => 'app_dam_dashboard', 'params' => 'failed' === $row['status'] ? ['filter' => 'failed'] : []],
                ],
            ),
            new FamilleTraitement(
                // Demandes du bouton « Enrichir ce qui manque » : en file tant que
                // le worker n'est pas passé, puis résultat par source.
                code: 'enrichissement',
                colonnes: 'r.fiche_id, r.requested_at, r.finished_at, r.resultat, f.label, f.type',
                depuis: 'pim_fiche_enrichment_run r INNER JOIN pim_fiche f ON f.id = r.fiche_id',
                condition: null,
                echec: null,
                tri: 'r.requested_at',
                ligne: static fn (array $row): array => [
                    'sujet' => sprintf('Enrichir ce qui manque · %s', (string) ($row['label'] ?? 'fiche')),
                    'statut' => null === $row['finished_at'] ? 'en_attente' : 'termine',
                    'erreur' => self::resumeEnrichissement($row['resultat']),
                    'quand' => (string) ($row['finished_at'] ?? $row['requested_at']),
                    'lien' => self::lienFiche((string) $row['type'], self::ulid($row['fiche_id'])),
                ],
            ),
            new FamilleTraitement(
                // Exports Excel du référentiel : la page de suivi (code unique)
                // reste consultable, le classeur téléchargeable jusqu'à expiration.
                code: 'export',
                colonnes: 'id, demandeur, statut, nb, erreur, requested_at, finished_at, expires_at, COALESCE(finished_at, requested_at) AS quand',
                depuis: 'pim_referentiel_export',
                condition: null,
                echec: "statut = 'echoue'",
                tri: 'quand',
                ligne: static fn (array $row): array => [
                    'sujet' => sprintf('Export Excel · %d fiche(s) · %s', (int) $row['nb'], (string) $row['demandeur']),
                    'statut' => (string) $row['statut'],
                    'erreur' => null === $row['erreur'] ? null : (string) $row['erreur'],
                    'quand' => (string) $row['quand'],
                    'lien' => ['route' => 'app_mdm_referentiel_export_suivi', 'params' => ['id' => self::ulid($row['id'])]],
                    'expire' => null === $row['expires_at'] ? null : (string) $row['expires_at'],
                ],
            ),
            new FamilleTraitement(
                // Attributions géographiques de visibilité : rattrapage global
                // (commande), attribution à la création, clic « Appliquer les
                // sites automatiques » — chaque traitement laisse sa trace.
                code: 'visibilite',
                colonnes: 'r.declencheur, r.nb_fiches, r.nb_attributions, r.detail, r.executed_at, r.fiche_id, f.label, f.type',
                depuis: 'pim_visibilite_geo_run r LEFT JOIN pim_fiche f ON f.id = r.fiche_id',
                condition: null,
                echec: null,
                tri: 'r.executed_at',
                ligne: static fn (array $row): array => [
                    'sujet' => match ((string) $row['declencheur']) {
                        'commande' => sprintf('Rattrapage géographique · %d fiche(s)', (int) $row['nb_fiches']),
                        'creation' => sprintf('Attribution à la création · %s', (string) ($row['label'] ?? 'fiche supprimée')),
                        default => sprintf('Sites automatiques · %s', (string) ($row['label'] ?? 'fiche supprimée')),
                    },
                    'statut' => 'termine',
                    'erreur' => self::resumeVisibilite((string) $row['declencheur'], (int) $row['nb_attributions'], $row['detail']),
                    'quand' => (string) $row['executed_at'],
                    'lien' => null === $row['fiche_id'] ? null : self::lienFiche((string) $row['type'], self::ulid($row['fiche_id'])),
                ],
            ),
            new FamilleTraitement(
                // Diffusion marketplace : l'état courant de chaque fiche diffusée,
                // du plus récemment traité au plus ancien (le worker met à jour la
                // ligne à chaque synchronisation). La reprise des échecs se fait
                // par `app:marketplace:sync --failed`.
                code: 'marketplace',
                colonnes: 'm.code, m.status, m.last_error, m.updated_at, m.fiche_id, f.label, f.type',
                depuis: 'etl_fiche_marketplace m LEFT JOIN pim_fiche f ON f.id = m.fiche_id',
                condition: null,
                echec: "m.status = 'failed'",
                tri: 'm.updated_at',
                ligne: static fn (array $row): array => [
                    'sujet' => sprintf('Diffusion · %s', (string) ($row['label'] ?? 'fiche '.$row['code'])),
                    'statut' => (string) $row['status'],
                    'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                    'quand' => (string) $row['updated_at'],
                    'lien' => null === $row['fiche_id'] || null === $row['type'] ? null : self::lienFiche((string) $row['type'], self::ulid($row['fiche_id'])),
                ],
            ),
            new FamilleTraitement(
                // Synchro sortante Salesforce (CSV e-mail) : l'état de chaque fiche
                // suivie — en attente d'envoi, en erreur (backoff, reprise
                // automatique une fois la cause corrigée), ou envoyée.
                code: 'salesforce',
                colonnes: 'e.fiche_id, e.dirty_at, e.sent_at, e.last_error, e.failure_count, f.label, f.type',
                depuis: 'etl_fiche_salesforce_export e LEFT JOIN pim_fiche f ON f.id = e.fiche_id',
                condition: null,
                echec: 'e.failure_count > 0',
                tri: 'COALESCE(e.sent_at, e.dirty_at)',
                ligne: static function (array $row): array {
                    $enAttente = null === $row['sent_at'] || $row['sent_at'] < $row['dirty_at'];

                    return [
                        'sujet' => sprintf('Salesforce · %s', (string) ($row['label'] ?? 'fiche supprimée')),
                        'statut' => (int) $row['failure_count'] > 0 ? 'en_erreur' : ($enAttente ? 'en_attente' : 'termine'),
                        'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                        'quand' => (string) ($row['sent_at'] ?? $row['dirty_at']),
                        'lien' => null === $row['type'] ? null : self::lienFiche((string) $row['type'], self::ulid($row['fiche_id'])),
                    ];
                },
            ),
            new FamilleTraitement(
                // Événements de l'outbox jamais relayés : la reprise se fait par
                // `app:outbox:failed:retry` (pas d'écran).
                code: 'outbox',
                colonnes: 'id, message_type, status, last_error, occurred_at',
                depuis: 'outbox_message',
                condition: null,
                echec: "status = 'failed'",
                tri: 'occurred_at',
                ligne: static function (array $row): array {
                    $type = (string) $row['message_type'];
                    $court = false === ($pos = strrpos($type, '\\')) ? $type : substr($type, $pos + 1);

                    return [
                        'sujet' => sprintf('%s · %s', $court, (string) $row['id']),
                        'statut' => (string) $row['status'],
                        'erreur' => null === $row['last_error'] ? null : (string) $row['last_error'],
                        'quand' => (string) $row['occurred_at'],
                        'lien' => null,
                    ];
                },
                dansJournal: false,
            ),
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
     * Résumé d'une attribution géographique : nombre de sites ajoutés, détaillé
     * par site pour le rattrapage global. Zéro se dit explicitement — le
     * traitement est passé et n'avait rien à faire.
     */
    private static function resumeVisibilite(string $declencheur, int $nbAttributions, mixed $detail): string
    {
        if (0 === $nbAttributions) {
            return 'commande' === $declencheur ? 'Aucune attribution : tout le stock est déjà couvert.' : 'Aucun site à ajouter : la fiche est déjà couverte.';
        }
        $resume = sprintf('%d attribution%s', $nbAttributions, $nbAttributions > 1 ? 's' : '');
        $parSite = is_string($detail) ? json_decode($detail, true) : null;
        if (is_array($parSite) && [] !== $parSite) {
            ksort($parSite);
            $parts = [];
            foreach ($parSite as $site => $nombre) {
                $parts[] = sprintf('%s : %d', (string) $site, (int) $nombre);
            }
            $resume .= ' — '.implode(' · ', $parts);
        }

        return $resume;
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
