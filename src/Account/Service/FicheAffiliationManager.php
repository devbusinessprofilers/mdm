<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheCollaborateurRepository;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FicheAffiliationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheAffiliationRepository $affiliations,
        private FicheCollaborateurRepository $collaborateurs,
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function toggleCollaborateur(FicheCollaborateur $collaborateur): void
    {
        $collaborateur->isActive() ? $collaborateur->deactivate() : $collaborateur->activate();
        $this->entityManager->flush();
    }

    public function invite(
        User $actor,
        Fiche $fiche,
        string $email,
        FicheAffiliationRole $role,
        bool $receivesRequests = false,
        string $firstName = '',
        string $lastName = '',
        string $language = 'fr',
        bool $traiteContenus = false,
        bool $traitePaiements = false,
        bool $repli = false,
    ): FicheAffiliation {
        return $this->transactional(function () use ($actor, $fiche, $email, $role, $receivesRequests, $firstName, $lastName, $language, $traiteContenus, $traitePaiements, $repli): FicheAffiliation {
            $this->lockAndAuthorize($actor, $fiche, $role);
            $collaborateur = $this->collaborateurs->findOneByEmail($email);
            if (null === $collaborateur) {
                $collaborateur = new FicheCollaborateur($email, $firstName, $lastName, $language);
                $this->entityManager->persist($collaborateur);
            } elseif (null !== $this->affiliations->findFor($collaborateur, $fiche)) {
                throw new \DomainException('Cet utilisateur est déjà affilié à cette fiche.');
            }

            $this->assertRecipientCapacity($fiche, $receivesRequests);
            $affiliation = new FicheAffiliation($collaborateur, $fiche, $role, $actor, $receivesRequests, $repli);
            if ($traiteContenus) {
                $affiliation->changeTraiteContenus(true);
            }
            if ($traitePaiements) {
                $affiliation->changeTraitePaiements(true);
            }
            $this->entityManager->persist($affiliation);

            return $affiliation;
        });
    }

    public function changeRole(User $actor, FicheAffiliation $affiliation, FicheAffiliationRole $role): FicheAffiliation
    {
        return $this->update($actor, $affiliation, $role, null);
    }

    public function changeReceivesRequests(User $actor, FicheAffiliation $affiliation, bool $receivesRequests): FicheAffiliation
    {
        return $this->update($actor, $affiliation, null, $receivesRequests);
    }

    public function update(
        User $actor,
        FicheAffiliation $affiliation,
        ?FicheAffiliationRole $role,
        ?bool $receivesRequests,
        ?bool $traiteContenus = null,
        ?bool $traitePaiements = null,
        ?bool $repli = null,
    ): FicheAffiliation {
        return $this->transactional(function () use ($actor, $affiliation, $role, $receivesRequests, $traiteContenus, $traitePaiements, $repli): FicheAffiliation {
            $this->lockAndAuthorize($actor, $affiliation->fiche(), $role ?? $affiliation->role(), $affiliation);
            if (null !== $receivesRequests) {
                $this->assertRecipientCapacity($affiliation->fiche(), $receivesRequests, $affiliation);
                $affiliation->changeReceivesRequests($receivesRequests);
            }
            if (null !== $traiteContenus) {
                $affiliation->changeTraiteContenus($traiteContenus);
            }
            if (null !== $traitePaiements) {
                $affiliation->changeTraitePaiements($traitePaiements);
            }
            if (null !== $repli) {
                $affiliation->changeRepli($repli);
            }
            if (null !== $role) {
                $affiliation->changeRole($role);
            }

            return $affiliation;
        });
    }

    public function remove(User $actor, FicheAffiliation $affiliation): void
    {
        $this->transactional(function () use ($actor, $affiliation): void {
            $this->lockAndAuthorize($actor, $affiliation->fiche(), $affiliation->role(), $affiliation, true);
            $this->entityManager->remove($affiliation);
        });
    }

    private function lockAndAuthorize(
        User $actor,
        Fiche $fiche,
        FicheAffiliationRole $targetRole,
        ?FicheAffiliation $target = null,
        bool $removal = false,
    ): void {
        if (!$actor->isActive()) {
            throw new \DomainException('Le compte est désactivé.');
        }
        $this->entityManager->lock($fiche, LockMode::PESSIMISTIC_WRITE);
        if ($this->isBp($actor)) {
            return;
        }
        throw new \DomainException('Ce compte PIM ne peut pas gérer les contacts de cette fiche.');
    }

    private function assertRecipientCapacity(Fiche $fiche, bool $receivesRequests, ?FicheAffiliation $excluding = null): void
    {
        $maximum = $this->parametres->int('compte.max_destinataires_demandes');
        if ($receivesRequests && $this->affiliations->countRequestRecipients($fiche, $excluding) >= $maximum) {
            throw new \DomainException(sprintf('Une fiche ne peut avoir que %d destinataires de demandes.', $maximum));
        }
    }

    private function isBp(User $user): bool
    {
        return [] !== array_intersect(['ROLE_BP_VALIDATOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function transactional(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction($operation);
    }
}
