<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\SavedView;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SavedViewManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param array<string, mixed> $filters */
    public function enregistrer(string $name, string $ownerId, string $ownerLabel, array $filters, bool $shared): SavedView
    {
        $vue = new SavedView($name, $ownerId, $ownerLabel, $filters, $shared);
        $this->entityManager->persist($vue);
        $this->entityManager->flush();

        return $vue;
    }

    public function supprimer(SavedView $vue): void
    {
        $this->entityManager->remove($vue);
        $this->entityManager->flush();
    }
}
