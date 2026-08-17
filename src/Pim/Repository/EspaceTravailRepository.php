<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

/**
 * Requêtes de l'espace de travail personnel : tout est rapporté à l'utilisateur
 * connecté via l'assignation (pim_fiche.assignee_id) et l'audit.
 */
final readonly class EspaceTravailRepository
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

    private const JOINS = <<<'SQL'
        LEFT JOIN pim_lieu l ON l.fiche_id = f.id AND f.type = 'lieu'
        LEFT JOIN pim_activite a ON a.fiche_id = f.id AND f.type = 'activite'
        LEFT JOIN pim_restaurant r ON r.fiche_id = f.id AND f.type = 'restaurant'
        LEFT JOIN pim_service_evenementiel sv ON sv.fiche_id = f.id AND f.type = 'service_evenementiel'
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    /** @return array{assignees: int, assignees_faibles: int, en_attente: int, ia: int} */
    public function compteurs(string $userId): array
    {
        $row = $this->connection->fetchAssociative(
            sprintf(
                <<<'SQL'
                SELECT
                    COUNT(*) AS assignees,
                    COALESCE(SUM(CASE WHEN COALESCE(%1$s, 0) < 50 THEN 1 ELSE 0 END), 0) AS assignees_faibles,
                    COALESCE(SUM(CASE WHEN f.status = 'en_attente_validation' THEN 1 ELSE 0 END), 0) AS en_attente,
                    COALESCE(SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM ocr_document_extraction ext
                        INNER JOIN ocr_suggestion sug ON sug.extraction_id = ext.id
                        WHERE ext.fiche_id = f.id AND sug.status = 'pending'
                    ) THEN 1 ELSE 0 END), 0) AS ia
                FROM pim_fiche f
                %2$s
                WHERE f.assignee_id = :id
                SQL,
                self::COMPLETENESS,
                self::JOINS,
            ),
            ['id' => $userId],
            ['id' => ParameterType::STRING],
        );

        return [
            'assignees' => (int) ($row['assignees'] ?? 0),
            'assignees_faibles' => (int) ($row['assignees_faibles'] ?? 0),
            'en_attente' => (int) ($row['en_attente'] ?? 0),
            'ia' => (int) ($row['ia'] ?? 0),
        ];
    }

    /**
     * Fiches assignées les moins complètes en premier : ce sont les priorités
     * de travail (pas de notion d'échéance en base pour l'instant).
     *
     * @return list<array{id: string, type: string, code: int, label: ?string, status: string, completeness: ?int}>
     */
    public function priorites(string $userId, int $limit = 7): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                SELECT f.id, f.type, f.code, f.label, f.status, %1$s AS completeness
                FROM pim_fiche f
                %2$s
                WHERE f.assignee_id = :id
                ORDER BY COALESCE(%1$s, 0) ASC, f.updated_at ASC
                LIMIT %3$d
                SQL,
                self::COMPLETENESS,
                self::JOINS,
                max(1, $limit),
            ),
            ['id' => $userId],
            ['id' => ParameterType::STRING],
        );
        $priorites = [];
        foreach ($rows as $row) {
            $priorites[] = [
                'id' => (string) Ulid::fromBinary((string) $row['id']),
                'type' => (string) $row['type'],
                'code' => (int) $row['code'],
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'status' => (string) $row['status'],
                'completeness' => null === $row['completeness'] ? null : (int) $row['completeness'],
            ];
        }

        return $priorites;
    }

    /**
     * La file de validation globale : toutes les fiches soumises, les plus
     * anciennes en premier — la vue Validateur de l'espace de travail.
     *
     * @return array{total: int, lignes: list<array{id: string, type: string, code: int,
     *     label: ?string, completeness: ?int, depuis: string}>}
     */
    public function fileValidation(int $limit = 7): array
    {
        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM pim_fiche WHERE status = 'en_attente_validation'",
        );
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                SELECT f.id, f.type, f.code, f.label, f.updated_at, %1$s AS completeness
                FROM pim_fiche f
                %2$s
                WHERE f.status = 'en_attente_validation'
                ORDER BY f.updated_at ASC
                LIMIT %3$d
                SQL,
                self::COMPLETENESS,
                self::JOINS,
                max(1, $limit),
            ),
        );
        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = [
                'id' => (string) Ulid::fromBinary((string) $row['id']),
                'type' => (string) $row['type'],
                'code' => (int) $row['code'],
                'label' => null === $row['label'] ? null : (string) $row['label'],
                'completeness' => null === $row['completeness'] ? null : (int) $row['completeness'],
                'depuis' => (string) $row['updated_at'],
            ];
        }

        return ['total' => $total, 'lignes' => $lignes];
    }

    /** @return array{creees: int, modifiees: int, validees: int, publiees: int} Mon activité depuis la date donnée. */
    public function activite(string $userId, \DateTimeImmutable $depuis): array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT
                COALESCE(SUM(ar.action = 'create'), 0) AS creees,
                COALESCE(SUM(ar.action = 'update'), 0) AS modifiees,
                COALESCE(SUM(ar.action = 'validation'), 0) AS validees,
                COALESCE(SUM(ar.action = 'publication'), 0) AS publiees
             FROM audit_revision ar
             WHERE ar.actor = :id AND ar.created_at >= :depuis",
            [
                'id' => $userId,
                'depuis' => $depuis->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
            ['id' => ParameterType::STRING, 'depuis' => ParameterType::STRING],
        );

        return [
            'creees' => (int) ($row['creees'] ?? 0),
            'modifiees' => (int) ($row['modifiees'] ?? 0),
            'validees' => (int) ($row['validees'] ?? 0),
            'publiees' => (int) ($row['publiees'] ?? 0),
        ];
    }
}
