<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Activité des équipes sur une période choisie, comptée en direct depuis
 * l'audit — contrairement aux snapshots, la période est celle que
 * l'utilisateur demande.
 */
final readonly class ActivitePeriodeRepository
{
    private const MAX_ACTEURS = 8;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{
     *     creees: int,
     *     modifiees: int,
     *     parActeur: list<array{actor: string, creees: int, modifiees: int, validees: int, publiees: int}>
     * }
     */
    public function activite(\DateTimeImmutable $depuis): array
    {
        $borne = $depuis->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $creees = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche WHERE created_at >= :depuis',
            ['depuis' => $borne],
            ['depuis' => ParameterType::STRING],
        );
        $modifiees = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM audit_revision WHERE action = 'update' AND created_at >= :depuis",
            ['depuis' => $borne],
            ['depuis' => ParameterType::STRING],
        );
        $rows = $this->connection->fetchAllAssociative(
            "SELECT ar.actor,
                COALESCE(SUM(ar.action = 'create'), 0) AS creees,
                COALESCE(SUM(ar.action = 'update'), 0) AS modifiees,
                COALESCE(SUM(ar.action = 'validation'), 0) AS validees,
                COALESCE(SUM(ar.action = 'publication'), 0) AS publiees
             FROM audit_revision ar
             WHERE ar.created_at >= :depuis
             GROUP BY ar.actor
             ORDER BY COUNT(*) DESC, ar.actor ASC
             LIMIT ".self::MAX_ACTEURS,
            ['depuis' => $borne],
            ['depuis' => ParameterType::STRING],
        );
        $parActeur = [];
        foreach ($rows as $row) {
            $parActeur[] = [
                'actor' => (string) $row['actor'],
                'creees' => (int) $row['creees'],
                'modifiees' => (int) $row['modifiees'],
                'validees' => (int) $row['validees'],
                'publiees' => (int) $row['publiees'],
            ];
        }

        return ['creees' => $creees, 'modifiees' => $modifiees, 'parActeur' => $parActeur];
    }
}
