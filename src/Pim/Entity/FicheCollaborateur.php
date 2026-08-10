<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\FicheCollaborateurRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FicheCollaborateurRepository::class)]
#[ORM\Table(name: 'pim_fiche_collaborateur')]
#[ORM\UniqueConstraint(name: 'UNIQ_FICHE_COLLABORATEUR_EMAIL', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class FicheCollaborateur
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private string $firstName;

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private string $lastName;

    #[ORM\Column(length: 8, options: ['default' => 'fr'])]
    private string $language;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    public function __construct(string $email, string $firstName = '', string $lastName = '', string $language = 'fr')
    {
        $this->id = new Ulid();
        $this->email = self::normalizeEmail($email);
        $this->changeProfile($firstName, $lastName, $language);
        $this->initializeTimestamps();
    }

    /** @return non-empty-string */
    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if ('' === $email) { throw new \InvalidArgumentException('L’adresse email ne peut pas être vide.'); }
        return $email;
    }
    public function id(): string { return (string) $this->id; }
    public function email(): string { return $this->email; }
    public function firstName(): string { return $this->firstName; }
    public function lastName(): string { return $this->lastName; }
    public function language(): string { return $this->language; }
    public function isActive(): bool { return $this->isActive; }
    public function changeProfile(string $firstName, string $lastName, string $language): void
    {
        $language = strtolower(trim($language));
        if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language)) {
            throw new \InvalidArgumentException('La langue doit être un code ISO valide.');
        }
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->language = $language;
        $this->touch();
    }

    public function phone(): ?string { return $this->phone; }
    public function changePhone(?string $phone): void
    {
        $phone = trim((string) $phone);
        $this->phone = '' === $phone ? null : $phone;
        $this->touch();
    }

    public function deactivate(): void { $this->isActive = false; $this->touch(); }
    public function activate(): void { $this->isActive = true; $this->touch(); }
}
