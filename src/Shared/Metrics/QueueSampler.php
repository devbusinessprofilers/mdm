<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Shared\Repository\FilesMessengerRepository;
use Doctrine\DBAL\Connection;

/**
 * Échantillonnage des files messenger_messages vers perf_sample, partagé
 * entre les workers (pas de 1 min via WorkerHeartbeatReporter) et le filet de
 * sécurité de /admin/performance (top-up quand aucun worker n'écrit, cas d'un
 * backlog qui monte workers arrêtés). Élection par verrou MySQL + garde de
 * fraîcheur : une seule série écrite par minute toutes instances confondues.
 *
 * Toutes les files déclarées sont échantillonnées, y compris vides : le
 * transport Doctrine supprime les messages traités, une file saine n'a donc
 * aucune ligne en table — sans le LEFT JOIN sur la liste des transports, les
 * graphiques ne montreraient que la DLQ.
 */
final class QueueSampler
{
    public static function sampleIfStale(Connection $connection, int $maxAgeS = 55): void
    {
        if (1 !== (int) $connection->fetchOne("SELECT GET_LOCK('perf_sample_queue', 0)")) {
            return;
        }
        try {
            $age = $connection->fetchOne(
                "SELECT TIMESTAMPDIFF(SECOND, MAX(sampled_at), NOW()) FROM perf_sample WHERE kind = 'queue'",
            );
            if (null !== $age && (int) $age < $maxAgeS) {
                return;
            }
            $connection->executeStatement(self::insertQueueSamplesSql());
        } finally {
            $connection->fetchOne("SELECT RELEASE_LOCK('perf_sample_queue')");
        }
    }

    /**
     * Files déclarées (FilesMessengerRepository::FILES) + files inattendues présentes
     * en table, chacune avec ses jauges — 0 pour une file vide.
     */
    private static function insertQueueSamplesSql(): string
    {
        $connues = FilesMessengerRepository::filesDeclarees();

        // m.queue_name IS NOT NULL : sur une file vide, la ligne non appariée
        // du LEFT JOIN a toutes ses colonnes m.* à NULL — sans cette garde,
        // « delivered_at IS NULL » compterait 1 message fantôme.
        return <<<SQL
            INSERT INTO perf_sample (sampled_at, kind, subject, metrics)
            SELECT NOW(), 'queue', files.nom, JSON_OBJECT(
                'pending', COALESCE(SUM(m.queue_name IS NOT NULL AND m.delivered_at IS NULL), 0),
                'delayed', COALESCE(SUM(m.queue_name IS NOT NULL AND m.delivered_at IS NULL AND m.available_at > UTC_TIMESTAMP()), 0),
                'oldest_age_s', COALESCE(TIMESTAMPDIFF(SECOND,
                    MIN(CASE WHEN m.delivered_at IS NULL THEN m.available_at END), UTC_TIMESTAMP()), 0))
            FROM ($connues UNION SELECT DISTINCT queue_name FROM messenger_messages) files
            LEFT JOIN messenger_messages m ON m.queue_name = files.nom
            GROUP BY files.nom
            SQL;
    }
}
