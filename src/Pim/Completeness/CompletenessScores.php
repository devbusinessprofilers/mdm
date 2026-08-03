<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

final readonly class CompletenessScores
{
    public function __construct(
        public int $global,
        public int $marketplace,
        public int $thematicSites,
        public int $salesforce,
        public int $providerPortal,
    ) {
        foreach (get_object_vars($this) as $score) {
            if ($score < 0 || $score > 100) {
                throw new \InvalidArgumentException('Un score de complétude doit être compris entre 0 et 100.');
            }
        }
    }

    /** @return array{marketplace: int, thematicSites: int, salesforce: int, providerPortal: int} */
    public function channels(): array
    {
        return [
            'marketplace' => $this->marketplace,
            'thematicSites' => $this->thematicSites,
            'salesforce' => $this->salesforce,
            'providerPortal' => $this->providerPortal,
        ];
    }
}
