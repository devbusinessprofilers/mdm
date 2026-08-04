<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Completeness\CompletenessScores;
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

final readonly class CompletenessRepository
{
    public function __construct(private Connection $connection) {}

    public function countPending(TypeFiche $type, int $revision): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE completeness_revision < :revision', self::table($type)),
            ['revision' => $revision],
            ['revision' => ParameterType::INTEGER],
        );
    }

    /** @return array{total: int|string, pending: int|string, last_calculation: string|null, minimum_score: int|string|null, average_score: int|string|null, maximum_score: int|string|null} */
    public function status(TypeFiche $type, int $revision): array
    {
        $row = $this->connection->fetchAssociative(sprintf(
            'SELECT COUNT(*) total, SUM(completeness_revision < :revision) pending, MAX(completeness_calculated_at) last_calculation, MIN(completeness_global) minimum_score, ROUND(AVG(completeness_global)) average_score, MAX(completeness_global) maximum_score FROM %s',
            self::table($type),
        ), ['revision' => $revision], ['revision' => ParameterType::INTEGER]);

        if (false === $row) {
            return ['total' => 0, 'pending' => 0, 'last_calculation' => null, 'minimum_score' => null, 'average_score' => null, 'maximum_score' => null];
        }

        return [
            'total' => is_int($row['total']) ? $row['total'] : (string) $row['total'],
            'pending' => is_int($row['pending']) ? $row['pending'] : (string) $row['pending'],
            'last_calculation' => null === $row['last_calculation'] ? null : (string) $row['last_calculation'],
            'minimum_score' => null === $row['minimum_score'] ? null : (is_int($row['minimum_score']) ? $row['minimum_score'] : (string) $row['minimum_score']),
            'average_score' => null === $row['average_score'] ? null : (is_int($row['average_score']) ? $row['average_score'] : (string) $row['average_score']),
            'maximum_score' => null === $row['maximum_score'] ? null : (is_int($row['maximum_score']) ? $row['maximum_score'] : (string) $row['maximum_score']),
        ];
    }

    /** @param array<string, CompletenessScores> $scoresByFiche */
    public function writeScores(TypeFiche $type, array $scoresByFiche, int $revision): int
    {
        if ([] === $scoresByFiche) {
            return 0;
        }
        $selects = [];
        $params = [];
        $types = [];
        foreach ($scoresByFiche as $ficheId => $scores) {
            $selects[] = 'SELECT ? AS fiche_id, ? AS score_global, ? AS score_marketplace, ? AS score_thematic, ? AS score_salesforce, ? AS score_portal';
            $params[] = Ulid::fromString($ficheId)->toBinary();
            $types[] = ParameterType::BINARY;
            foreach ([$scores->global, $scores->marketplace, $scores->thematicSites, $scores->salesforce, $scores->providerPortal] as $score) {
                $params[] = $score;
                $types[] = ParameterType::INTEGER;
            }
        }
        $params[] = $revision;
        $types[] = ParameterType::INTEGER;
        $sql = sprintf(
            'UPDATE %s domain_score JOIN (%s) calculated ON calculated.fiche_id = domain_score.fiche_id '
            .'SET domain_score.completeness_global = calculated.score_global, domain_score.completeness_marketplace = calculated.score_marketplace, '
            .'domain_score.completeness_thematic_sites = calculated.score_thematic, domain_score.completeness_salesforce = calculated.score_salesforce, '
            .'domain_score.completeness_provider_portal = calculated.score_portal, domain_score.completeness_calculated_at = UTC_TIMESTAMP(), '
            .'domain_score.completeness_revision = ?',
            self::table($type),
            implode(' UNION ALL ', $selects),
        );

        return (int) $this->connection->executeStatement($sql, $params, $types);
    }

    private static function table(TypeFiche $type): string
    {
        return match ($type) {
            TypeFiche::Lieu => 'pim_lieu',
            TypeFiche::Activite => 'pim_activite',
            TypeFiche::Restaurant => 'pim_restaurant',
            TypeFiche::ServiceEvenementiel => 'pim_service_evenementiel',
            default => throw new \InvalidArgumentException('Type de fiche non pris en charge par la complétude.'),
        };
    }
}
