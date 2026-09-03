<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class InternalFicheMutationPolicy
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Les validateurs modifient la fiche sans effectuer de transition implicite.
     * Les éditeurs conservent le comportement métier de Fiche::markChanged().
     *
     * @template T
     *
     * @param callable(): T $mutation
     *
     * @return T
     */
    public function execute(Fiche $fiche, callable $mutation): mixed
    {
        if (!$this->authorizationChecker->isGranted('ROLE_BP_VALIDATOR')) {
            return $mutation();
        }

        return $fiche->preserveWorkflowDuring($mutation);
    }
}
