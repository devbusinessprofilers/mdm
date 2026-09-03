<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use Doctrine\DBAL\Connection;

/**
 * Lecture de la table `messenger_messages` du transport Doctrine : la seule
 * porte d'entrée pour compter les files (journal /outils, santé, métriques,
 * alerte, page performance). Les messages traités sont supprimés par le
 * transport : une file saine n'a aucune ligne, d'où la liste des files
 * déclarées pour montrer les vides à 0. `available_at` est stocké en UTC.
 */
final readonly class FilesMessengerRepository
{
    /** Files des transports Doctrine déclarés dans messenger.yaml. */
    public const FILES = ['pim', 'dam', 'etl', 'enrichment', 'completeness', 'mail', 'marketplace', 'failed'];

    public function __construct(private Connection $connection)
    {
    }

    /** Messages de la DLQ `failed`. */
    public function compterEchecs(): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'");
    }

    /** @return array<string, int> messages non livrés par file, hors `failed` */
    public function enAttenteParFile(): array
    {
        return array_map(intval(...), $this->connection->fetchAllKeyValue(
            "SELECT queue_name, COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name <> 'failed' GROUP BY queue_name",
        ));
    }

    /** @return array<string, int> âge (s) du plus ancien message non livré par file, hors `failed` */
    public function ageDuPlusAncienParFile(): array
    {
        return array_map(static fn (mixed $s): int => max(0, (int) $s), $this->connection->fetchAllKeyValue(
            "SELECT queue_name, TIMESTAMPDIFF(SECOND, MIN(available_at), UTC_TIMESTAMP()) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name <> 'failed' GROUP BY queue_name",
        ));
    }

    /** @return array<string, int> toutes les lignes par file, `failed` comprise */
    public function totalParFile(): array
    {
        return array_map(intval(...), $this->connection->fetchAllKeyValue(
            'SELECT queue_name, COUNT(*) FROM messenger_messages GROUP BY queue_name',
        ));
    }

    /**
     * Les quatre états qui partitionnent la table (il n'y a pas de
     * « terminés » : le transport les supprime).
     *
     * - en_file   : en attente, pas encore pris par un worker
     * - en_cours  : réservé par un worker (livré, pas encore acquitté)
     * - planifies : en attente d'un retry (disponible dans le futur)
     * - echecs    : DLQ `failed`
     *
     * @return array{en_file: int, en_cours: int, planifies: int, echecs: int}
     */
    public function etats(): array
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
            // neuve). Les écrans ne doivent jamais tomber pour autant.
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
     * Jauges par file, files déclarées comprises (à 0 quand vides) et files
     * inattendues présentes en table.
     *
     * @return list<array{queue_name: string, pending: int, delayed: int, en_cours: int, oldest_age_s: int}>
     */
    public function parFile(): array
    {
        // m.queue_name IS NOT NULL : sur une file vide, la ligne non appariée
        // du LEFT JOIN a toutes ses colonnes m.* à NULL — sans cette garde,
        // « delivered_at IS NULL » compterait 1 message fantôme.
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT files.nom AS queue_name,
                   COALESCE(SUM(m.queue_name IS NOT NULL AND m.delivered_at IS NULL AND m.available_at <= UTC_TIMESTAMP()), 0) AS pending,
                   COALESCE(SUM(m.queue_name IS NOT NULL AND m.delivered_at IS NULL AND m.available_at > UTC_TIMESTAMP()), 0) AS delayed,
                   COALESCE(SUM(m.delivered_at IS NOT NULL), 0) AS en_cours,
                   COALESCE(TIMESTAMPDIFF(SECOND,
                       MIN(CASE WHEN m.delivered_at IS NULL THEN m.available_at END), UTC_TIMESTAMP()), 0) AS oldest_age_s
            FROM ({$this->filesDeclarees()} UNION SELECT DISTINCT queue_name FROM messenger_messages) files
            LEFT JOIN messenger_messages m ON m.queue_name = files.nom
            GROUP BY files.nom
            ORDER BY files.nom
            SQL);

        return array_map(static fn (array $row): array => [
            'queue_name' => (string) $row['queue_name'],
            'pending' => (int) $row['pending'],
            'delayed' => (int) $row['delayed'],
            'en_cours' => (int) $row['en_cours'],
            'oldest_age_s' => max(0, (int) $row['oldest_age_s']),
        ], $rows);
    }

    /** Sous-requête `SELECT 'pim' AS nom UNION SELECT 'dam' AS nom …` des files déclarées. */
    public static function filesDeclarees(): string
    {
        return implode(' UNION ', array_map(
            static fn (string $nom): string => sprintf("SELECT '%s' AS nom", $nom),
            self::FILES,
        ));
    }
}
