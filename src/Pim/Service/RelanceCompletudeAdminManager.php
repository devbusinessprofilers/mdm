<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\FicheRelancePlanifiee;
use Doctrine\ORM\EntityManagerInterface;

/** Actions d'administration sur le lot de relances de complétude planifié. */
final readonly class RelanceCompletudeAdminManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function exclure(FicheRelancePlanifiee $planifiee): void
    {
        $planifiee->exclure();
        $this->entityManager->flush();
    }
}
