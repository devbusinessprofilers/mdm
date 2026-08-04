<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\CompletenessRepository;

final readonly class CompletenessScoreWriter
{
    public function __construct(private CompletenessRepository $repository)
    {
    }

    /** @param array<string, CompletenessScores> $scoresByFiche */
    public function write(TypeFiche $type, array $scoresByFiche, int $revision): int
    {
        return $this->repository->writeScores($type, $scoresByFiche, $revision);
    }
}
