<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use App\Dashboard\Model\LogFilter;
use Doctrine\DBAL\Connection;

/**
 * Lecture de la table log_entry pour la visionneuse de /admin/performance.
 * Requêtes servies par les index (level, logged_at) et (channel, logged_at) ;
 * la recherche plein-texte reste un LIKE assumé (table à rétention courte).
 */
final readonly class LogEntryRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function recherche(LogFilter $filtre): array
    {
        $conditions = ['level >= :niveau'];
        $params = ['niveau' => $filtre->niveauMin];
        if (null !== $filtre->canal) {
            $conditions[] = 'channel = :canal';
            $params['canal'] = $filtre->canal;
        }
        if ('' !== $filtre->q) {
            $conditions[] = 'message LIKE :q';
            $params['q'] = '%'.addcslashes($filtre->q, '%_\\').'%';
        }
        if (null !== $filtre->depuis) {
            $conditions[] = 'logged_at >= :depuis';
            $params['depuis'] = $filtre->depuis->format('Y-m-d H:i:s');
        }
        if (null !== $filtre->jusqua) {
            $conditions[] = 'logged_at <= :jusqua';
            $params['jusqua'] = $filtre->jusqua->format('Y-m-d H:i:s');
        }
        $where = implode(' AND ', $conditions);

        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM log_entry WHERE $where",
            $params,
        );
        $offset = ($filtre->page - 1) * LogFilter::PAR_PAGE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, logged_at, channel, level, message, context, extra, request_id, hostname
             FROM log_entry WHERE $where
             ORDER BY logged_at DESC, id DESC
             LIMIT ".LogFilter::PAR_PAGE." OFFSET $offset",
            $params,
        );
        $items = array_map(static function (array $row): array {
            $row['contexte_lisible'] = self::pretty((string) ($row['context'] ?? ''));
            $row['extra_lisible'] = self::pretty((string) ($row['extra'] ?? ''));

            return $row;
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /** JSON stocké → version indentée pour le dépliage dans la visionneuse. */
    private static function pretty(string $json): ?string
    {
        if ('' === $json) {
            return null;
        }
        $decoded = json_decode($json, true);

        return null === $decoded ? $json : (json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) ?: $json);
    }

    /**
     * Compteurs par niveau sur les dernières 24 h, pour les jauges en tête de
     * visionneuse.
     *
     * @return array<int, int> valeur Monolog => nombre
     */
    public function compteursParNiveau(): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT level, COUNT(*) FROM log_entry
             WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY level ORDER BY level',
        );

        return array_map(intval(...), $rows);
    }

    /** @return list<string> */
    public function canauxConnus(): array
    {
        /** @var list<string> $canaux */
        $canaux = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT channel FROM log_entry ORDER BY channel',
        );

        return $canaux;
    }

    /** @return int lignes supprimées */
    public function purge(int $jours = 15, int $lot = 5000): int
    {
        $total = 0;
        do {
            $supprimees = (int) $this->connection->executeStatement(
                'DELETE FROM log_entry WHERE logged_at < DATE_SUB(NOW(), INTERVAL :jours DAY) LIMIT '.$lot,
                ['jours' => $jours],
            );
            $total += $supprimees;
        } while ($supprimees === $lot);

        return $total;
    }
}
