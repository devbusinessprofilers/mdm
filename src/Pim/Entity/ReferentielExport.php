<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\ReferentielExportRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Demande d'export Excel du référentiel : créée au clic (la page de suivi
 * /referentiel/exports/{id} est partageable et se rouvre plus tard), générée
 * en tâche de fond par le worker, listée dans le journal /outils (famille
 * « Historique des exports »). L'id ULID est le code unique de l'export.
 */
#[ORM\Entity(repositoryClass: ReferentielExportRepository::class)]
#[ORM\Table(name: 'pim_referentiel_export')]
#[ORM\Index(name: 'IDX_REFERENTIEL_EXPORT_REQUESTED', columns: ['requested_at'])]
class ReferentielExport
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_TERMINEE = 'terminee';
    public const STATUT_ECHOUEE = 'echoue';
    public const STATUT_EXPIREE = 'expiree';

    /** Rétention du classeur sur le bucket privé après génération. */
    public const RETENTION = '+30 days';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /** Identifiant de l'utilisateur qui a demandé l'export (affichage journal). */
    #[ORM\Column(length: 180)]
    private string $demandeur;

    #[ORM\Column(length: 16)]
    private string $statut = self::STATUT_EN_ATTENTE;

    /** @var list<string> clés cochées du FicheExportColonnesCatalogue */
    #[ORM\Column(type: 'json')]
    private array $colonnes;

    /** @var list<string>|null ids ULID de la sélection cochée ; null = « tout le résultat filtré » */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $ids;

    /** @var array<string, mixed> filtres du référentiel au moment de la demande (résolution du « tout ») */
    #[ORM\Column(type: 'json')]
    private array $filtres;

    /** Nombre de fiches : estimé à la demande, recompté à la génération. */
    #[ORM\Column(type: 'integer')]
    private int $nb;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** Date de suppression du classeur du bucket (30 jours après génération). */
    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $erreur = null;

    /**
     * @param list<string>         $colonnes
     * @param list<string>|null    $ids
     * @param array<string, mixed> $filtres
     */
    public function __construct(string $demandeur, array $colonnes, ?array $ids, array $filtres, int $nb)
    {
        $this->id = new Ulid();
        $this->demandeur = $demandeur;
        $this->colonnes = $colonnes;
        $this->ids = $ids;
        $this->filtres = $filtres;
        $this->nb = $nb;
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function idString(): string
    {
        return (string) $this->id;
    }

    public function demandeur(): string
    {
        return $this->demandeur;
    }

    public function statut(): string
    {
        return $this->statut;
    }

    /** @return list<string> */
    public function colonnes(): array
    {
        return $this->colonnes;
    }

    /** @return list<string>|null */
    public function ids(): ?array
    {
        return $this->ids;
    }

    /** @return array<string, mixed> */
    public function filtres(): array
    {
        return $this->filtres;
    }

    public function nb(): int
    {
        return $this->nb;
    }

    public function requestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function erreur(): ?string
    {
        return $this->erreur;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function enAttente(): bool
    {
        return self::STATUT_EN_ATTENTE === $this->statut;
    }

    public function estTerminee(): bool
    {
        return self::STATUT_TERMINEE === $this->statut;
    }

    /** Le classeur a dépassé sa rétention (purgé, ou en attente de purge). */
    public function estExpiree(): bool
    {
        if (self::STATUT_EXPIREE === $this->statut) {
            return true;
        }

        return $this->estTerminee() && null !== $this->expiresAt && $this->expiresAt <= new \DateTimeImmutable();
    }

    public function telechargeable(): bool
    {
        return $this->estTerminee() && !$this->estExpiree();
    }

    public function demarrer(): void
    {
        $this->statut = self::STATUT_EN_COURS;
    }

    public function terminer(int $nb): void
    {
        $this->statut = self::STATUT_TERMINEE;
        $this->nb = $nb;
        $this->finishedAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable(self::RETENTION);
    }

    public function expirer(): void
    {
        $this->statut = self::STATUT_EXPIREE;
    }

    public function echouer(string $erreur): void
    {
        $this->statut = self::STATUT_ECHOUEE;
        $this->erreur = $erreur;
        $this->finishedAt = new \DateTimeImmutable();
    }

    /** Nom proposé au téléchargement — la référence courte rend le fichier traçable. */
    public function filename(): string
    {
        return sprintf(
            'referentiel-export-%s-%s.xlsx',
            $this->requestedAt->format('Ymd-Hi'),
            strtolower(substr((string) $this->id, -6)),
        );
    }
}
