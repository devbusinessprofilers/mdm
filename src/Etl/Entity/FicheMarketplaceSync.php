<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * État de diffusion d'une fiche sur la marketplace : présence connue, dernière
 * séquence appliquée et dernière erreur. Une ligne existe dès qu'un envoi a été
 * tenté ; son absence signifie que la marketplace n'a jamais reçu la fiche.
 */
#[ORM\Entity(repositoryClass: FicheMarketplaceSyncRepository::class)]
#[ORM\Table(name: 'etl_fiche_marketplace')]
#[ORM\Index(name: 'IDX_ETL_MARKETPLACE_STATUS', columns: ['status', 'updated_at'])]
class FicheMarketplaceSync
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $ficheId;

    /** Code fiche = syspad_id marketplace : conservé pour retirer une fiche disparue. */
    #[ORM\Column(options: ['unsigned' => true])]
    private int $code;

    #[ORM\Column(length: 16, enumType: MarketplaceSyncStatus::class)]
    private MarketplaceSyncStatus $status;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $lastSequence = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Ulid $ficheId, int $code)
    {
        $this->ficheId = $ficheId;
        $this->code = $code;
        $this->status = MarketplaceSyncStatus::Failed;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function ficheId(): Ulid
    {
        return $this->ficheId;
    }

    public function code(): int
    {
        return $this->code;
    }

    public function status(): MarketplaceSyncStatus
    {
        return $this->status;
    }

    public function lastSequence(): ?string
    {
        return $this->lastSequence;
    }

    public function syncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function recordSynced(string $sequence): void
    {
        $this->status = MarketplaceSyncStatus::Synced;
        $this->lastSequence = $sequence;
        $this->syncedAt = new \DateTimeImmutable();
        $this->lastError = null;
        $this->touch();
    }

    public function recordRemoved(string $sequence): void
    {
        $this->status = MarketplaceSyncStatus::Removed;
        $this->lastSequence = $sequence;
        $this->lastError = null;
        $this->touch();
    }

    public function recordFailure(string $error): void
    {
        $this->status = MarketplaceSyncStatus::Failed;
        $this->lastError = $error;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
