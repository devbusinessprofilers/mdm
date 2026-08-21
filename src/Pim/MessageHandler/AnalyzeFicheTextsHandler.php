<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Entity\Fiche;
use App\Pim\Message\AnalyzeFicheTexts;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\TextDuplicateDetector;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class AnalyzeFicheTextsHandler
{
    public function __construct(
        private FicheRepository $repository,
        private TextDuplicateDetector $detector,
    ) {
    }

    public function __invoke(AnalyzeFicheTexts $message): void
    {
        $fiche = $this->repository->find(Ulid::fromString($message->ficheId));
        if ($fiche instanceof Fiche) {
            $this->detector->analyze($fiche);
        }
    }
}
