<?php

declare(strict_types=1);

namespace App\Account\Entity;

use App\Account\Repository\AccountInvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: AccountInvitationRepository::class)]
#[ORM\Table(name: 'account_invitation')]
#[ORM\Index(name: 'IDX_ACCOUNT_INVITATION_USER', columns: ['user_id'])]
class AccountInvitation
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invalidatedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @param int $validiteHeures durée de validité (paramètre compte.invitation_validite_heures, défaut historique 24 h) */
    public function __construct(User $user, int $validiteHeures = 24)
    {
        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(sprintf('+%d hours', $validiteHeures));
    }

    public function id(): string { return $this->id; }
    public function user(): User { return $this->user; }
    public function expiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function acceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }
    public function invalidatedAt(): ?\DateTimeImmutable { return $this->invalidatedAt; }
    public function isUsable(): bool { return null === $this->acceptedAt && null === $this->invalidatedAt && !$this->user->isDeleted() && $this->expiresAt > new \DateTimeImmutable(); }
    public function accept(): void
    {
        if (!$this->isUsable()) { throw new \DomainException('Cette invitation est expirée ou déjà utilisée.'); }
        $this->acceptedAt = new \DateTimeImmutable();
    }

    public function invalidate(): void
    {
        if (null === $this->acceptedAt && null === $this->invalidatedAt) {
            $this->invalidatedAt = new \DateTimeImmutable();
        }
    }
}
