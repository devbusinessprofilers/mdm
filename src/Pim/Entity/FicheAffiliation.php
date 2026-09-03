<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FicheAffiliationRepository::class)]
#[ORM\Table(name: 'pim_fiche_affiliation')]
#[ORM\UniqueConstraint(name: 'UNIQ_AFFILIATION_COLLABORATEUR_FICHE', columns: ['collaborateur_id', 'fiche_id'])]
#[ORM\Index(name: 'IDX_AFFILIATION_FICHE_ROLE', columns: ['fiche_id', 'role'])]
#[ORM\Index(name: 'IDX_AFFILIATION_FICHE_REQUESTS', columns: ['fiche_id', 'receives_requests'])]
#[ORM\HasLifecycleCallbacks]
class FicheAffiliation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FicheCollaborateur $collaborateur;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\Column(length: 24, enumType: FicheAffiliationRole::class)]
    private FicheAffiliationRole $role;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_user_id', nullable: true, onDelete: 'RESTRICT')]
    private ?User $createdByUser = null;

    /** Colonne d'audit legacy, conservée pour ne pas reconstruire les affiliations existantes. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_collaborateur_id', nullable: true, onDelete: 'RESTRICT')]
    private ?FicheCollaborateur $createdByCollaborateur = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $receivesRequests;

    /** Contact de repli : le service référencement remplace le contact prestataire, aucun accès n'est envoyé. */
    #[ORM\Column(options: ['default' => false])]
    private bool $repli;

    /** Droits maquette : le collaborateur traite les contenus de la fiche. */
    #[ORM\Column(options: ['default' => false])]
    private bool $traiteContenus;

    /** Droits maquette : le collaborateur traite les paiements de la fiche. */
    #[ORM\Column(options: ['default' => false])]
    private bool $traitePaiements;

    public function __construct(FicheCollaborateur $collaborateur, Fiche $fiche, FicheAffiliationRole $role, User $createdBy, bool $receivesRequests = false, bool $repli = false)
    {
        $this->id = new Ulid();
        $this->collaborateur = $collaborateur;
        $this->fiche = $fiche;
        $this->role = $role;
        $this->receivesRequests = $receivesRequests;
        $this->repli = $repli;
        $this->traiteContenus = false;
        $this->traitePaiements = false;
        $this->createdByUser = $createdBy;
        $this->initializeTimestamps();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function idString(): string
    {
        return (string) $this->id;
    }

    public function collaborateur(): FicheCollaborateur
    {
        return $this->collaborateur;
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function role(): FicheAffiliationRole
    {
        return $this->role;
    }

    public function receivesRequests(): bool
    {
        return $this->receivesRequests;
    }

    public function repli(): bool
    {
        return $this->repli;
    }

    public function traiteContenus(): bool
    {
        return $this->traiteContenus;
    }

    public function traitePaiements(): bool
    {
        return $this->traitePaiements;
    }

    public function changeRepli(bool $value): void
    {
        $this->repli = $value;
        $this->touch();
    }

    public function changeTraiteContenus(bool $value): void
    {
        $this->traiteContenus = $value;
        $this->touch();
    }

    public function changeTraitePaiements(bool $value): void
    {
        $this->traitePaiements = $value;
        $this->touch();
    }

    public function createdBy(): User|FicheCollaborateur
    {
        return $this->createdByUser ?? $this->createdByCollaborateur ?? throw new \LogicException('Auteur manquant.');
    }

    public function changeRole(FicheAffiliationRole $role): void
    {
        $this->role = $role;
        $this->touch();
    }

    public function changeReceivesRequests(bool $value): void
    {
        $this->receivesRequests = $value;
        $this->touch();
    }
}
