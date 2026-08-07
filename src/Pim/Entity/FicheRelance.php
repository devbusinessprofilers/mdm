<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\FicheRelanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Journal append-only des relances de complétude envoyées aux collaborateurs.
 * Sert d'anti-spam (pas de nouvelle relance avant le cooldown) et de trace
 * auditable de qui a été prévenu, quand et à quel score.
 */
#[ORM\Entity(repositoryClass: FicheRelanceRepository::class)]
#[ORM\Table(name: 'pim_fiche_relance')]
#[ORM\Index(name: 'IDX_FICHE_RELANCE_FICHE_SENT', columns: ['fiche_id', 'sent_at'])]
class FicheRelance
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $completenessAtSend;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $recipientEmails;

    /** @param list<string> $recipientEmails */
    public function __construct(Fiche $fiche, int $completenessAtSend, array $recipientEmails)
    {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->sentAt = new \DateTimeImmutable();
        $this->completenessAtSend = max(0, min(100, $completenessAtSend));
        $this->recipientEmails = $recipientEmails;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function sentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function completenessAtSend(): int
    {
        return $this->completenessAtSend;
    }

    /** @return list<string> */
    public function recipientEmails(): array
    {
        return $this->recipientEmails;
    }
}
