<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\SuggestionAction;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Enum\SuggestionStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Suggestion d'enrichissement générique attachée à une fiche, produite par une
 * source externe gratuite (Sirene aujourd'hui) et arbitrée en un clic dans le
 * bloc « Suggestions en attente ». Deux natures : remplir un champ, ou proposer
 * l'archivage d'un établissement détecté comme cessé.
 *
 * Ce châssis est réutilisable par les prochaines sources (Geoapify,
 * DATAtourisme, Wikidata). Il ne remplace pas les suggestions d'adresse BAN,
 * qui restent en ligne sur la Localisation.
 */
#[ORM\Entity(repositoryClass: \App\Pim\Repository\FicheSuggestionRepository::class)]
#[ORM\Table(name: 'pim_fiche_suggestion')]
#[ORM\Index(name: 'IDX_FICHE_SUGGESTION_ATTENTE', columns: ['statut', 'fiche_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_FICHE_SUGGESTION_CLE', columns: ['fiche_id', 'source', 'champ'])]
class FicheSuggestion
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'fiche_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\Column(length: 32, enumType: SuggestionSource::class)]
    private SuggestionSource $source;

    #[ORM\Column(length: 32, enumType: SuggestionAction::class)]
    private SuggestionAction $action;

    /** Champ visé quand l'action est RemplirChamp (ex. « info_legale_siret ») ; sentinelle stable sinon. */
    #[ORM\Column(length: 64)]
    private string $champ;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $valeurActuelle;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $valeurProposee;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 4, nullable: true)]
    private ?string $score;

    /**
     * Données machine pour l'application (ex. codes LOV à fusionner, booléen) —
     * l'affichage reste dans valeurProposee.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload;

    #[ORM\Column(length: 16, enumType: SuggestionStatut::class)]
    private SuggestionStatut $statut = SuggestionStatut::EnAttente;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /** @param array<string, mixed>|null $payload */
    public function __construct(
        Fiche $fiche,
        SuggestionSource $source,
        SuggestionAction $action,
        string $champ,
        string $label,
        ?string $valeurActuelle,
        ?string $valeurProposee,
        ?float $score = null,
        ?array $payload = null,
    ) {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->source = $source;
        $this->action = $action;
        $this->champ = $champ;
        $this->label = $label;
        $this->valeurActuelle = self::trim($valeurActuelle);
        $this->valeurProposee = self::trim($valeurProposee);
        $this->score = null === $score ? null : number_format(max(0.0, min(1.0, $score)), 4, '.', '');
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string { return (string) $this->id; }
    public function fiche(): Fiche { return $this->fiche; }
    public function source(): SuggestionSource { return $this->source; }
    public function action(): SuggestionAction { return $this->action; }
    public function champ(): string { return $this->champ; }
    public function label(): string { return $this->label; }
    public function valeurActuelle(): ?string { return $this->valeurActuelle; }
    public function valeurProposee(): ?string { return $this->valeurProposee; }
    public function score(): ?float { return null === $this->score ? null : (float) $this->score; }
    /** @return array<string, mixed>|null */
    public function payload(): ?array { return $this->payload; }
    public function statut(): SuggestionStatut { return $this->statut; }
    public function isPending(): bool { return SuggestionStatut::EnAttente === $this->statut; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function decidedBy(): ?string { return $this->decidedBy; }
    public function decidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }

    /**
     * Rafraîchit une suggestion existante avec un constat plus récent (garde l'écart à jour).
     *
     * @param array<string, mixed>|null $payload
     */
    public function rafraichir(string $label, ?string $valeurActuelle, ?string $valeurProposee, ?float $score, ?array $payload = null): void
    {
        if (!$this->isPending()) {
            throw new \DomainException('Une suggestion arbitrée est immuable.');
        }
        $this->label = $label;
        $this->valeurActuelle = self::trim($valeurActuelle);
        $this->valeurProposee = self::trim($valeurProposee);
        $this->score = null === $score ? null : number_format(max(0.0, min(1.0, $score)), 4, '.', '');
        $this->payload = $payload;
    }

    public function accepter(string $actor): void
    {
        $this->decider(SuggestionStatut::Acceptee, $actor);
    }

    public function ignorer(string $actor): void
    {
        $this->decider(SuggestionStatut::Ignoree, $actor);
    }

    private function decider(SuggestionStatut $statut, string $actor): void
    {
        if (!$this->isPending()) {
            throw new \DomainException('Cette suggestion a déjà été arbitrée.');
        }
        $this->statut = $statut;
        $this->decidedBy = $actor;
        $this->decidedAt = new \DateTimeImmutable();
    }

    private static function trim(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : mb_substr($value, 0, 500);
    }
}
