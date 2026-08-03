<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Completeness\CompletenessManager;
use App\Pim\Entity\Fiche;
use App\Pim\Message\CalculateFicheCompleteness;
use App\Pim\Repository\FicheRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class CalculateFicheCompletenessHandler
{
    public function __construct(private FicheRepository $fiches, private CompletenessManager $manager)
    {
    }

    public function __invoke(CalculateFicheCompleteness $message): void
    {
        $fiche = $this->fiches->find(Ulid::fromString($message->ficheId));
        if ($fiche instanceof Fiche) { $this->manager->calculate($fiche); }
    }
}
