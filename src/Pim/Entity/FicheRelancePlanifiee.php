<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\StatutRelancePlanifiee;
use App\Pim\Repository\FicheRelancePlanifieeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Ligne du lot de relances de complétude préparé avant envoi : la préparation
 * (lundi 8h ou bouton « Relancer l'analyse ») snapshote fiche, score et
 * destinataires pour vérification dans /admin/relances-completude, puis
 * l'envoi (lundi 14h ou bouton « Envoyer maintenant ») consomme les lignes
 * encore planifiées. Les emails effectivement partis restent journalisés
 * dans pim_fiche_relance.
 */
#[ORM\Entity(repositoryClass: FicheRelancePlanifieeRepository::class)]
#[ORM\Table(name: 'pim_fiche_relance_planifiee')]
#[ORM\Index(name: 'IDX_RELANCE_PLANIFIEE_STATUT_PREPARED', columns: ['statut', 'prepared_at'])]
#[ORM\Index(name: 'IDX_RELANCE_PLANIFIEE_FICHE', columns: ['fiche_id'])]
class FicheRelancePlanifiee
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $preparedAt;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $completenessAtPreparation;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $recipientEmails;

    #[ORM\Column(length: 16, enumType: StatutRelancePlanifiee::class)]
    private StatutRelancePlanifiee $statut;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    /** @param list<string> $recipientEmails */
    public function __construct(Fiche $fiche, \DateTimeImmutable $preparedAt, int $completenessAtPreparation, array $recipientEmails)
    {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->preparedAt = $preparedAt;
        $this->completenessAtPreparation = max(0, min(100, $completenessAtPreparation));
        $this->recipientEmails = $recipientEmails;
        $this->statut = StatutRelancePlanifiee::Planifiee;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function preparedAt(): \DateTimeImmutable
    {
        return $this->preparedAt;
    }

    public function completenessAtPreparation(): int
    {
        return $this->completenessAtPreparation;
    }

    /** @return list<string> */
    public function recipientEmails(): array
    {
        return $this->recipientEmails;
    }

    public function statut(): StatutRelancePlanifiee
    {
        return $this->statut;
    }

    public function processedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function motif(): ?string
    {
        return $this->motif;
    }

    public function estPlanifiee(): bool
    {
        return StatutRelancePlanifiee::Planifiee === $this->statut;
    }

    public function exclure(): void
    {
        $this->transitionner(StatutRelancePlanifiee::Exclue, null);
    }

    public function annuler(string $motif): void
    {
        $this->transitionner(StatutRelancePlanifiee::Annulee, $motif);
    }

    public function marquerEnvoyee(): void
    {
        $this->transitionner(StatutRelancePlanifiee::Envoyee, null);
    }

    public function marquerIgnoree(string $motif): void
    {
        $this->transitionner(StatutRelancePlanifiee::Ignoree, $motif);
    }

    private function transitionner(StatutRelancePlanifiee $statut, ?string $motif): void
    {
        if (!$this->estPlanifiee()) {
            throw new \LogicException(sprintf('Relance planifiée %s déjà traitée (%s).', $this->id, $this->statut->value));
        }
        $this->statut = $statut;
        $this->motif = $motif;
        $this->processedAt = new \DateTimeImmutable();
    }
}
