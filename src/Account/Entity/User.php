<?php

declare(strict_types=1);

namespace App\Account\Entity;

use App\Account\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'account_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_ACCOUNT_USER_EMAIL', columns: ['email'])]
#[ORM\Index(name: 'IDX_ACCOUNT_USER_DELETED', columns: ['deleted_at'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** Mot de passe posé pour l'utilisateur (et non par lui) : à changer à la prochaine connexion. */
    #[ORM\Column(options: ['default' => false])]
    private bool $mustChangePassword = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @param list<string> $roles */
    public function __construct(string $email, array $roles = [])
    {
        $now = new \DateTimeImmutable();

        $normalizedEmail = self::normalizeEmail($email);
        if ('' === $normalizedEmail) {
            throw new \InvalidArgumentException('The email address cannot be empty.');
        }

        $this->id = (string) new Ulid();
        $this->email = $normalizedEmail;
        $this->roles = array_values(array_unique($roles));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function changeEmail(string $email): void
    {
        $normalizedEmail = self::normalizeEmail($email);
        if ('' === $normalizedEmail) {
            throw new \InvalidArgumentException('The email address cannot be empty.');
        }

        $this->email = $normalizedEmail;
        $this->touch();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
        // Un mot de passe fraîchement enregistré lève l'obligation de changement.
        $this->mustChangePassword = false;
        $this->touch();
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function requirePasswordChange(): void
    {
        $this->mustChangePassword = true;
        $this->touch();
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = array_values(array_unique($roles));
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->touch();
    }

    public function activate(): void
    {
        if ($this->isDeleted()) {
            throw new \DomainException('Un compte supprimé ne peut pas être réactivé.');
        }
        $this->isActive = true;
        $this->touch();
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function anonymize(): void
    {
        if ($this->isDeleted()) {
            throw new \DomainException('Ce compte est déjà supprimé.');
        }

        $this->deletedAt = new \DateTimeImmutable();
        $this->email = sprintf('deleted+%s@invalid.local', strtolower($this->id));
        $this->password = null;
        $this->roles = [];
        $this->isActive = false;
        $this->touch();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function eraseCredentials(): void
    {
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
