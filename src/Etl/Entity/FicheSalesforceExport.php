<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Repository\FicheSalesforceExportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Suivi de la synchronisation Salesforce par CSV e-mail d'une fiche.
 *
 * `dirtyAt` est repoussé à chaque mutation (point de convergence
 * IndexFicheHandler) : l'envoi Produits (au fil de l'eau) et l'envoi Salles
 * (nocturne groupé) traitent les lignes en retard (`sentAt`/`sallesSentAt`
 * antérieurs à `dirtyAt`). Coalescence intrinsèque : une rafale de modales
 * enregistrées à la suite ne laisse qu'une ligne à envoyer.
 */
#[ORM\Entity(repositoryClass: FicheSalesforceExportRepository::class)]
#[ORM\Table(name: 'etl_fiche_salesforce_export')]
#[ORM\Index(name: 'IDX_ETL_SF_EXPORT_DIRTY', columns: ['dirty_at'])]
class FicheSalesforceExport
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $ficheId;

    /** Code fiche = ID_PRODUCT Salesforce (= syspad_id). */
    #[ORM\Column(options: ['unsigned' => true])]
    private int $code;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dirtyAt;

    /** Dernier envoi Produits réussi. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    /** Dernier envoi Salles réussi (envoi nocturne groupé). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sallesSentAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $failureCount = 0;

    /** Prochain essai Produits autorisé après un échec (backoff exponentiel plafonné). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $retryAt = null;

    public function __construct(Ulid $ficheId, int $code)
    {
        $this->ficheId = $ficheId;
        $this->code = $code;
        $this->dirtyAt = new \DateTimeImmutable();
    }

    public function ficheId(): Ulid
    {
        return $this->ficheId;
    }

    public function code(): int
    {
        return $this->code;
    }

    public function dirtyAt(): \DateTimeImmutable
    {
        return $this->dirtyAt;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function sallesSentAt(): ?\DateTimeImmutable
    {
        return $this->sallesSentAt;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function failureCount(): int
    {
        return $this->failureCount;
    }

    public function retryAt(): ?\DateTimeImmutable
    {
        return $this->retryAt;
    }

    /** Nouvelle mutation à synchroniser : repousse l'échéance des deux envois. */
    public function markDirty(): void
    {
        $this->dirtyAt = new \DateTimeImmutable();
    }

    /**
     * @param \DateTimeImmutable $borne échéance (dirtyAt) observée au début du
     *                                  traitement : marquer envoyé jusqu'à cette
     *                                  valeur laisse « sale » toute mutation
     *                                  survenue pendant l'envoi (dirtyAt avancé).
     */
    public function recordProduitsSent(\DateTimeImmutable $borne): void
    {
        $this->sentAt = $borne;
        $this->lastError = null;
        $this->failureCount = 0;
        $this->retryAt = null;
    }

    public function recordSallesSent(\DateTimeImmutable $borne): void
    {
        $this->sallesSentAt = $borne;
    }

    /**
     * Backoff exponentiel plafonné à 24 h (2, 4, 8… minutes) : une ligne en
     * échec permanent ne doit ni être retentée à chaque tic d'une minute, ni
     * occuper la tête du lot au détriment des fiches saines.
     */
    public function recordFailure(string $error): void
    {
        $this->lastError = $error;
        $this->failureCount = min($this->failureCount + 1, 20);
        $minutes = min(2 ** $this->failureCount, 1440);
        $this->retryAt = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', $minutes));
    }
}
