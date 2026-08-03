<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

final readonly class CompletenessScoreWriter
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string, CompletenessScores> $scoresByFiche */
    public function write(TypeFiche $type, array $scoresByFiche, int $revision): int
    {
        if ([] === $scoresByFiche) {
            return 0;
        }
        $table = match ($type) {
            TypeFiche::Lieu => 'pim_lieu',
            TypeFiche::Activite => 'pim_activite',
            TypeFiche::Restaurant => 'pim_restaurant',
            TypeFiche::ServiceEvenementiel => 'pim_service_evenementiel',
            default => throw new \InvalidArgumentException('Type de fiche non pris en charge par la complétude.'),
        };
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
            $table,
            implode(' UNION ALL ', $selects),
        );

        return (int) $this->connection->executeStatement($sql, $params, $types);
    }
}
