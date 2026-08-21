<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\DuplicateReviewStatus;
use App\Pim\Repository\TextDuplicateAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Arbitrage d'une alerte de doublon de texte depuis l'écran Qualité.
 * « confirmer » acte un doublon légitime, « ignorer » écarte un faux positif ;
 * les deux sortent l'alerte de la file en attente.
 */
final readonly class TextDuplicateArbitre
{
    public function __construct(
        private TextDuplicateAlertRepository $alerts,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function decide(string $alertId, string $decision, string $actor): bool
    {
        if (!Ulid::isValid($alertId)) {
            return false;
        }
        $alert = $this->alerts->find(Ulid::fromString($alertId));
        if (null === $alert || DuplicateReviewStatus::Pending !== $alert->status()) {
            return false;
        }

        'confirmer' === $decision ? $alert->accept($actor) : $alert->resolve($actor);
        $this->entityManager->flush();

        return true;
    }
}
