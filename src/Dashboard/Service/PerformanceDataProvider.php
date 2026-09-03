<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Shared\Entity\PerfSample;
use App\Shared\Entity\WorkerHeartbeat;
use App\Shared\Metrics\PerfSampleRepository;
use App\Shared\Metrics\QueueSampler;
use App\Shared\Metrics\WorkerHeartbeatRepository;
use Doctrine\DBAL\Connection;

/**
 * Agrège heartbeats, série temporelle et files Messenger en un instantané
 * pour /admin/performance (cartes, graphiques, tableaux). Les fenêtres
 * glissantes se calculent par différence de cumuls entre échantillons — un
 * échantillon manqué ne fausse pas le résultat, et les instances successives
 * d'un même worker (--time-limit=3600 ⇒ nouveau pid) s'additionnent par nom.
 */
final readonly class PerformanceDataProvider
{
    /** Workers attendus (services docker-compose) : une carte chacun, même sans heartbeat. */
    public const WORKERS_ATTENDUS = [
        'worker-pim', 'worker-dam', 'worker-batch', 'worker-mail', 'worker-outbox', 'cron-scheduler',
    ];

    public const FENETRES_MINUTES = [15, 60, 360, 1440];

    // Fraîcheur du heartbeat (flush max toutes les 5 s en activité).
    private const SEUIL_ACTIF_S = 30;
    private const SEUIL_RETARD_S = 120;
    // Un message peut légitimement geler la boucle d'événements ce temps-là.
    private const SEUIL_MESSAGE_LONG_S = 1800;

    public function __construct(
        private WorkerHeartbeatRepository $heartbeats,
        private PerfSampleRepository $samples,
        private FailedMessageActions $failedMessages,
        private Connection $connection,
    ) {
    }

    /**
     * Instantané complet pour l'endpoint JSON (cartes + graphiques).
     *
     * @return array<string, mixed>
     */
    public function data(int $fenetreMinutes): array
    {
        $fenetreMinutes = in_array($fenetreMinutes, self::FENETRES_MINUTES, true) ? $fenetreMinutes : 15;
        $this->samples->topUpQueueSamples();

        $heartbeats = $this->heartbeats->recents();
        $workerSeries = $this->samples->series(PerfSample::KIND_WORKER, $fenetreMinutes);
        $queueSeries = $this->samples->series(PerfSample::KIND_QUEUE, $fenetreMinutes);

        return [
            'generatedAt' => date(\DATE_ATOM),
            'fenetreMinutes' => $fenetreMinutes,
            'workers' => $this->workers($heartbeats, $workerSeries, $fenetreMinutes),
            'queues' => $this->queues($heartbeats),
            'failed' => ['total' => $this->failedMessages->compter()],
            'messages' => $this->messages($heartbeats),
            'series' => $this->series($workerSeries, $queueSeries, $fenetreMinutes),
        ];
    }

    /**
     * Données des tableaux rendus serveur (fragment pollé).
     *
     * @return array{queues: list<array<string, mixed>>, failed: list<array<string, mixed>>}
     */
    public function tableaux(): array
    {
        return [
            'queues' => $this->queues($this->heartbeats->recents()),
            'failed' => $this->failedMessages->lister(),
        ];
    }

    /**
     * Une carte par worker attendu : la plus récente instance porte l'état,
     * la fenêtre de samples porte charge et débit.
     *
     * @param list<array<string, mixed>> $heartbeats
     * @param list<array<string, mixed>> $workerSeries
     *
     * @return list<array<string, mixed>>
     */
    private function workers(array $heartbeats, array $workerSeries, int $fenetreMinutes): array
    {
        $parNom = [];
        foreach ($heartbeats as $hb) {
            // recents() est trié par (nom, last_seen DESC) : la première
            // instance rencontrée est la plus récente.
            $parNom[$hb['worker_name']] ??= $hb;
        }
        $fenetres = $this->fenetresParInstance($workerSeries);

        $noms = self::WORKERS_ATTENDUS;
        foreach (array_keys($parNom) as $nom) {
            if (!in_array($nom, $noms, true)) {
                $noms[] = $nom; // worker inattendu (consume manuel) : affiché quand même
            }
        }

        $cartes = [];
        foreach ($noms as $nom) {
            $hb = $parNom[$nom] ?? null;
            if (null === $hb) {
                $cartes[] = [
                    'key' => null, 'name' => $nom, 'transports' => [],
                    'etat' => 'inconnu', 'lastSeenAgoS' => null, 'uptimeS' => null,
                    'chargePct' => null, 'msgParMin' => null,
                    'memoryBytes' => null, 'memoryPeakBytes' => null,
                    'enCours' => null,
                    'totaux' => ['handled' => 0, 'failed' => 0, 'retried' => 0],
                ];
                continue;
            }
            [$chargePct, $msgParMin] = $this->chargeEtDebit($nom, $parNom, $fenetres, $fenetreMinutes);
            $cartes[] = [
                'key' => $hb['worker_key'],
                'name' => $nom,
                'transports' => $hb['transports'],
                'etat' => $this->etat($hb),
                'lastSeenAgoS' => $hb['last_seen_ago_s'],
                'uptimeS' => $hb['uptime_s'],
                'chargePct' => $chargePct,
                'msgParMin' => $msgParMin,
                'memoryBytes' => $hb['memory_bytes'],
                'memoryPeakBytes' => $hb['memory_peak_bytes'],
                'enCours' => null !== $hb['current_message_class']
                    ? ['classe' => $this->classeCourte($hb['current_message_class']), 'depuisS' => $hb['current_message_since_s']]
                    : null,
                'totaux' => [
                    'handled' => $hb['handled_total'],
                    'failed' => $hb['failed_total'],
                    'retried' => $hb['retried_total'],
                ],
            ];
        }

        return $cartes;
    }

    /**
     * @param array<string, mixed> $hb
     */
    private function etat(array $hb): string
    {
        if ('cron-scheduler' === $hb['worker_name']) {
            // Intermittent par design (fenêtres de 5 min toutes les 15 min) :
            // jamais compté comme incident.
            return 'planifie';
        }
        if (WorkerHeartbeat::STATUS_STOPPED === $hb['status']) {
            return 'inactif';
        }
        if ($hb['last_seen_ago_s'] < self::SEUIL_ACTIF_S) {
            return 'actif';
        }
        // Un message long bloque la boucle d'événements : plus aucun battement
        // pendant handle(). Le message en cours flushé à la réception évite un
        // faux rouge.
        if (null !== $hb['current_message_class']
            && ($hb['current_message_since_s'] ?? 0) < self::SEUIL_MESSAGE_LONG_S) {
            return 'occupe';
        }

        return $hb['last_seen_ago_s'] < self::SEUIL_RETARD_S ? 'retard' : 'inactif';
    }

    /**
     * Premier/dernier échantillon de la fenêtre pour chaque instance
     * (worker_key), pour les deltas de cumuls.
     *
     * @param list<array<string, mixed>> $workerSeries
     *
     * @return array<string, array{premier: array<string, mixed>, dernier: array<string, mixed>, name: string}>
     */
    private function fenetresParInstance(array $workerSeries): array
    {
        $fenetres = [];
        foreach ($workerSeries as $sample) {
            $key = $sample['subject'];
            $fenetres[$key] ??= ['premier' => $sample, 'dernier' => $sample, 'name' => (string) ($sample['metrics']['name'] ?? '')];
            $fenetres[$key]['dernier'] = $sample; // trié par sampled_at croissant
        }

        return $fenetres;
    }

    /**
     * @param array<string, array<string, mixed>>                                                              $parNom
     * @param array<string, array{premier: array<string, mixed>, dernier: array<string, mixed>, name: string}> $fenetres
     *
     * @return array{0: ?float, 1: ?float} [charge %, messages/min]
     */
    private function chargeEtDebit(string $nom, array $parNom, array $fenetres, int $fenetreMinutes): array
    {
        $busyMs = 0.0;
        $handled = 0;
        $wallMs = 0.0;
        foreach ($fenetres as $fenetre) {
            if ($fenetre['name'] !== $nom) {
                continue;
            }
            $span = ($fenetre['premier']['age_s'] - $fenetre['dernier']['age_s']) * 1000.0;
            if ($span <= 0) {
                continue; // instance à échantillon unique : ignorée ici
            }
            $wallMs += $span;
            $busyMs += max(0.0, (float) ($fenetre['dernier']['metrics']['busy_ms'] ?? 0) - (float) ($fenetre['premier']['metrics']['busy_ms'] ?? 0));
            $handled += max(0, (int) ($fenetre['dernier']['metrics']['handled'] ?? 0) - (int) ($fenetre['premier']['metrics']['handled'] ?? 0));
        }
        if ($wallMs <= 0.0) {
            // Pas encore deux échantillons (worker fraîchement démarré) :
            // ratio depuis le démarrage du processus, affiché comme approché.
            $hb = $parNom[$nom] ?? null;
            if (null === $hb || $hb['uptime_s'] < 1) {
                return [null, null];
            }
            $uptimeMs = $hb['uptime_s'] * 1000.0;

            return [
                min(100.0, round(100.0 * $hb['busy_ms_total'] / $uptimeMs, 1)),
                round($hb['handled_total'] / max(1.0, $hb['uptime_s'] / 60.0), 1),
            ];
        }

        return [
            min(100.0, round(100.0 * $busyMs / $wallMs, 1)),
            round($handled / ($wallMs / 60000.0), 1),
        ];
    }

    /**
     * État courant des files : requête directe sur messenger_messages (la
     * table est petite, les messages traités en sont supprimés — d'où le
     * LEFT JOIN sur les files déclarées, pour montrer les files vides à 0).
     *
     * @param list<array<string, mixed>> $heartbeats
     *
     * @return list<array<string, mixed>>
     */
    private function queues(array $heartbeats): array
    {
        $connues = implode(' UNION ', array_map(
            static fn (string $nom): string => sprintf("SELECT '%s' AS nom", $nom),
            QueueSampler::KNOWN_QUEUES,
        ));
        try {
            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT files.nom AS queue_name,
                       -- m.queue_name IS NOT NULL : ligne non appariée du LEFT JOIN
                       -- (file vide), sinon comptée comme un message fantôme.
                       COALESCE(SUM(m.queue_name IS NOT NULL AND m.delivered_at IS NULL AND m.available_at <= UTC_TIMESTAMP()), 0) AS pending,
                       COALESCE(SUM(m.queue_name IS NOT NULL AND m.delivered_at IS NULL AND m.available_at > UTC_TIMESTAMP()), 0) AS delayed,
                       COALESCE(SUM(m.delivered_at IS NOT NULL), 0) AS en_cours,
                       COALESCE(TIMESTAMPDIFF(SECOND,
                           MIN(CASE WHEN m.delivered_at IS NULL THEN m.available_at END), UTC_TIMESTAMP()), 0) AS oldest_age_s
                FROM ($connues UNION SELECT DISTINCT queue_name FROM messenger_messages) files
                LEFT JOIN messenger_messages m ON m.queue_name = files.nom
                GROUP BY files.nom
                ORDER BY files.nom
                SQL);
        } catch (\Throwable) {
            return []; // table absente (base neuve) : la page ne tombe pas
        }

        $actifs = array_values(array_filter(
            $heartbeats,
            static fn (array $hb): bool => WorkerHeartbeat::STATUS_RUNNING === $hb['status'] && $hb['last_seen_ago_s'] < self::SEUIL_RETARD_S,
        ));

        return array_map(static fn (array $row): array => [
            'name' => (string) $row['queue_name'],
            'pending' => (int) $row['pending'],
            'delayed' => (int) $row['delayed'],
            'enCours' => (int) $row['en_cours'],
            'oldestAgeS' => max(0, (int) $row['oldest_age_s']),
            'consumers' => count(array_filter(
                $actifs,
                static fn (array $hb): bool => in_array($row['queue_name'], $hb['transports'], true),
            )),
        ], $rows);
    }

    /**
     * Temps moyens par classe de message : cumuls depuis le démarrage des
     * instances récentes (affiché comme tel sur la page).
     *
     * @param list<array<string, mixed>> $heartbeats
     *
     * @return list<array{classe: string, count: int, avgMs: int, failed: int}>
     */
    private function messages(array $heartbeats): array
    {
        $stats = [];
        foreach ($heartbeats as $hb) {
            foreach ($hb['message_stats'] as $classe => $s) {
                $entree = $stats[$classe] ?? ['count' => 0, 'ms_sum' => 0.0, 'failed' => 0];
                $entree['count'] += (int) ($s['count'] ?? 0);
                $entree['ms_sum'] += (float) ($s['ms_sum'] ?? 0);
                $entree['failed'] += (int) ($s['failed'] ?? 0);
                $stats[$classe] = $entree;
            }
        }
        uasort($stats, static fn (array $a, array $b): int => $b['ms_sum'] <=> $a['ms_sum']);

        $lignes = [];
        foreach ($stats as $classe => $s) {
            $lignes[] = [
                'classe' => $this->classeCourte((string) $classe),
                'count' => $s['count'],
                'avgMs' => $s['count'] > 0 ? (int) round($s['ms_sum'] / $s['count']) : 0,
                'failed' => $s['failed'],
            ];
        }

        return array_slice($lignes, 0, 30);
    }

    /**
     * Séries alignées à la minute pour chart.js : labels en timestamps unix,
     * trous laissés à null (spanGaps côté client).
     *
     * @param list<array<string, mixed>> $workerSeries
     * @param list<array<string, mixed>> $queueSeries
     *
     * @return array<string, mixed>
     */
    private function series(array $workerSeries, array $queueSeries, int $fenetreMinutes): array
    {
        $now = time();
        $labels = [];
        $indexParMinute = [];
        for ($i = $fenetreMinutes; $i >= 0; --$i) {
            $ts = (int) (floor(($now - $i * 60) / 60) * 60);
            $indexParMinute[$ts] = count($labels);
            $labels[] = $ts;
        }
        $bucket = static function (int $ageS) use ($now, $indexParMinute): ?int {
            $ts = (int) (floor(($now - $ageS) / 60) * 60);

            return $indexParMinute[$ts] ?? null;
        };
        $vide = static fn (): array => array_fill(0, count($labels), null);

        // Files : jauges directes.
        $profondeur = [];
        foreach ($queueSeries as $sample) {
            $i = $bucket($sample['age_s']);
            if (null === $i) {
                continue;
            }
            $profondeur[$sample['subject']] ??= $vide();
            $profondeur[$sample['subject']][$i] = (int) ($sample['metrics']['pending'] ?? 0);
        }

        // Workers : dérivées des cumuls entre échantillons consécutifs d'une
        // même instance, agrégées par nom de worker (les redémarrages horaires
        // changent le pid, pas le nom).
        $debit = [];
        $charge = [];
        $memoire = [];
        $precedent = [];
        foreach ($workerSeries as $sample) {
            $key = $sample['subject'];
            $nom = (string) ($sample['metrics']['name'] ?? $key);
            $i = $bucket($sample['age_s']);
            if (null !== $i) {
                $memoire[$nom] ??= $vide();
                $memoire[$nom][$i] = round(((int) ($sample['metrics']['memory'] ?? 0)) / 1048576, 1);
            }
            $avant = $precedent[$key] ?? null;
            $precedent[$key] = $sample;
            if (null === $avant || null === $i) {
                continue;
            }
            $spanMs = ($avant['age_s'] - $sample['age_s']) * 1000.0;
            if ($spanMs <= 0) {
                continue;
            }
            $busyDelta = max(0.0, (float) ($sample['metrics']['busy_ms'] ?? 0) - (float) ($avant['metrics']['busy_ms'] ?? 0));
            $charge[$nom] ??= $vide();
            $charge[$nom][$i] = min(100.0, round(100.0 * $busyDelta / $spanMs, 1));

            $transportsAvant = (array) ($avant['metrics']['transports'] ?? []);
            foreach ((array) ($sample['metrics']['transports'] ?? []) as $transport => $stats) {
                $delta = max(0, (int) ($stats['handled'] ?? 0) - (int) ($transportsAvant[$transport]['handled'] ?? 0));
                $debit[$transport] ??= $vide();
                $debit[$transport][$i] = ($debit[$transport][$i] ?? 0) + round($delta / ($spanMs / 60000.0), 1);
            }
        }

        return [
            'labels' => $labels,
            'debit' => $debit,
            'profondeur' => $profondeur,
            'charge' => $charge,
            'memoire' => $memoire,
        ];
    }

    private function classeCourte(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return false === $pos ? $fqcn : substr($fqcn, $pos + 1);
    }
}
