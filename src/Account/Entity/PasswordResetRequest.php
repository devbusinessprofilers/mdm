<?php

declare(strict_types=1);

namespace App\Account\Entity;

use App\Account\Repository\PasswordResetRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PasswordResetRequestRepository::class)]
#[ORM\Table(name: 'account_password_reset_request')]
#[ORM\Index(name: 'IDX_ACCOUNT_PASSWORD_RESET_USER', columns: ['user_id'])]
final class PasswordResetRequest
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
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invalidatedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @param int $validiteHeures durée de validité (paramètre compte.reset_validite_heures, défaut historique 1 h) */
    public function __construct(User $user, int $validiteHeures = 1)
    {
        $this->id = (string) new Ulid();
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(sprintf('+%d hours', $validiteHeures));
    }

    public function id(): string { return $this->id; }
    public function user(): User { return $this->user; }
    public function expiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function usedAt(): ?\DateTimeImmutable { return $this->usedAt; }
    public function invalidatedAt(): ?\DateTimeImmutable { return $this->invalidatedAt; }

    public function isUsable(): bool
    {
        return null === $this->usedAt
            && null === $this->invalidatedAt
            && !$this->user->isDeleted()
            && $this->expiresAt > new \DateTimeImmutable();
    }

    public function use(): void
    {
        if (!$this->isUsable()) {
            throw new \DomainException('Cette demande est expirée, invalidée ou déjà utilisée.');
        }
        $this->usedAt = new \DateTimeImmutable();
    }

    public function invalidate(): void
    {
        if (null === $this->usedAt && null === $this->invalidatedAt) {
            $this->invalidatedAt = new \DateTimeImmutable();
        }
    }
}
