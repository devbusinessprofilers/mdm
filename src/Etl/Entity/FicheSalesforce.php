<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Repository\FicheSalesforceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Données Salesforce d'une fiche (notes RSE, évaluation client, procédure
 * judiciaire, contrats avec comptes), rafraîchies par
 * app:salesforce:refresh-fiches et poussées vers la marketplace dans le
 * payload de sync. Données techniques : jamais éditées dans le PIM, une
 * ligne n'existe que si la fiche est connue de Salesforce (Product2.ID__c).
 */
#[ORM\Entity(repositoryClass: FicheSalesforceRepository::class)]
#[ORM\Table(name: 'etl_fiche_salesforce')]
class FicheSalesforce
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $ficheId;

    /** Code fiche = Product2.ID__c = bp_produit.syspad_id. */
    #[ORM\Column(options: ['unsigned' => true])]
    private int $code;

    #[ORM\Column(nullable: true)]
    private ?float $noteAchatsResponsables = null;

    #[ORM\Column(nullable: true)]
    private ?float $noteImpactEnvironnemental = null;

    #[ORM\Column(nullable: true)]
    private ?float $noteImpactSocial = null;

    #[ORM\Column(nullable: true)]
    private ?float $noteMobilite = null;

    #[ORM\Column(nullable: true)]
    private ?float $noteLabelsEtDemarcheQualite = null;

    #[ORM\Column(nullable: true)]
    private ?float $noteGlobale = null;

    #[ORM\Column(nullable: true)]
    private ?float $evaluationClient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $procedureJudiciaire = null;

    /** @var list<string> Valeurs brutes des champs Contrat_avec_comptes_*. */
    #[ORM\Column(type: Types::JSON)]
    private array $contratsComptes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $refreshedAt;

    public function __construct(Ulid $ficheId, int $code)
    {
        $this->ficheId = $ficheId;
        $this->code = $code;
        $this->refreshedAt = new \DateTimeImmutable();
    }

    public function ficheId(): Ulid
    {
        return $this->ficheId;
    }

    public function code(): int
    {
        return $this->code;
    }

    public function noteAchatsResponsables(): ?float
    {
        return $this->noteAchatsResponsables;
    }

    public function noteImpactEnvironnemental(): ?float
    {
        return $this->noteImpactEnvironnemental;
    }

    public function noteImpactSocial(): ?float
    {
        return $this->noteImpactSocial;
    }

    public function noteMobilite(): ?float
    {
        return $this->noteMobilite;
    }

    public function noteLabelsEtDemarcheQualite(): ?float
    {
        return $this->noteLabelsEtDemarcheQualite;
    }

    public function noteGlobale(): ?float
    {
        return $this->noteGlobale;
    }

    public function evaluationClient(): ?float
    {
        return $this->evaluationClient;
    }

    public function procedureJudiciaire(): ?string
    {
        return $this->procedureJudiciaire;
    }

    /** @return list<string> */
    public function contratsComptes(): array
    {
        return $this->contratsComptes;
    }

    public function refreshedAt(): \DateTimeImmutable
    {
        return $this->refreshedAt;
    }

    /**
     * Applique les valeurs du refresh ; retourne vrai si au moins une a
     * changé (garde-diff : rien ne part vers la marketplace sinon).
     *
     * @param list<string> $contratsComptes
     */
    public function mettreAJour(
        ?float $noteAchatsResponsables,
        ?float $noteImpactEnvironnemental,
        ?float $noteImpactSocial,
        ?float $noteMobilite,
        ?float $noteLabelsEtDemarcheQualite,
        ?float $noteGlobale,
        ?float $evaluationClient,
        ?string $procedureJudiciaire,
        array $contratsComptes,
    ): bool {
        $avant = [
            $this->noteAchatsResponsables,
            $this->noteImpactEnvironnemental,
            $this->noteImpactSocial,
            $this->noteMobilite,
            $this->noteLabelsEtDemarcheQualite,
            $this->noteGlobale,
            $this->evaluationClient,
            $this->procedureJudiciaire,
            $this->contratsComptes,
        ];
        $this->noteAchatsResponsables = $noteAchatsResponsables;
        $this->noteImpactEnvironnemental = $noteImpactEnvironnemental;
        $this->noteImpactSocial = $noteImpactSocial;
        $this->noteMobilite = $noteMobilite;
        $this->noteLabelsEtDemarcheQualite = $noteLabelsEtDemarcheQualite;
        $this->noteGlobale = $noteGlobale;
        $this->evaluationClient = $evaluationClient;
        $this->procedureJudiciaire = $procedureJudiciaire;
        $this->contratsComptes = $contratsComptes;
        $this->refreshedAt = new \DateTimeImmutable();

        return $avant !== [
            $this->noteAchatsResponsables,
            $this->noteImpactEnvironnemental,
            $this->noteImpactSocial,
            $this->noteMobilite,
            $this->noteLabelsEtDemarcheQualite,
            $this->noteGlobale,
            $this->evaluationClient,
            $this->procedureJudiciaire,
            $this->contratsComptes,
        ];
    }
}
