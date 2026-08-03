<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Completeness\CompletenessScores;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait CompletenessScoresTrait
{
    #[ORM\Column(name: 'completeness_global', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $completenessGlobal = 0;

    #[ORM\Column(name: 'completeness_marketplace', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $completenessMarketplace = 0;

    #[ORM\Column(name: 'completeness_thematic_sites', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $completenessThematicSites = 0;

    #[ORM\Column(name: 'completeness_salesforce', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $completenessSalesforce = 0;

    #[ORM\Column(name: 'completeness_provider_portal', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $completenessProviderPortal = 0;

    #[ORM\Column(name: 'completeness_calculated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completenessCalculatedAt = null;

    #[ORM\Column(name: 'completeness_revision', options: ['unsigned' => true, 'default' => 0])]
    private int $completenessRevision = 0;

    public function completeness(): int
    {
        return $this->completenessGlobal;
    }

    public function completenessMarketplace(): int
    {
        return $this->completenessMarketplace;
    }

    public function completenessThematicSites(): int
    {
        return $this->completenessThematicSites;
    }

    public function completenessSalesforce(): int
    {
        return $this->completenessSalesforce;
    }

    public function completenessProviderPortal(): int
    {
        return $this->completenessProviderPortal;
    }

    public function completenessCalculatedAt(): ?\DateTimeImmutable
    {
        return $this->completenessCalculatedAt;
    }

    public function completenessRevision(): int
    {
        return $this->completenessRevision;
    }

    public function applyCompleteness(CompletenessScores $scores, int $revision): void
    {
        $this->completenessGlobal = $scores->global;
        $this->completenessMarketplace = $scores->marketplace;
        $this->completenessThematicSites = $scores->thematicSites;
        $this->completenessSalesforce = $scores->salesforce;
        $this->completenessProviderPortal = $scores->providerPortal;
        $this->completenessRevision = max(0, $revision);
        $this->completenessCalculatedAt = new \DateTimeImmutable();
    }

    /** @return array{marketplace: int, thematicSites: int, salesforce: int, providerPortal: int} */
    public function completenessByChannel(): array
    {
        return [
            'marketplace' => $this->completenessMarketplace,
            'thematicSites' => $this->completenessThematicSites,
            'salesforce' => $this->completenessSalesforce,
            'providerPortal' => $this->completenessProviderPortal,
        ];
    }
}
