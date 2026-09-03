<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FicheRepository::class)]
#[ORM\Table(name: 'pim_fiche')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_PIM_FICHE_CODE',
    columns: ['code'],
),]
#[ORM\Index(
    name: 'IDX_PIM_FICHE_TYPE_UPDATED',
    columns: ['type', 'updated_at', 'id'],
),]
#[ORM\Index(name: 'IDX_PIM_FICHE_TYPE_ID', columns: ['type', 'id'])]
#[ORM\Index(
    name: 'IDX_PIM_FICHE_TYPE_STATUS_UPDATED',
    columns: ['type', 'status', 'updated_at', 'id'],
),]
#[ORM\Index(
    name: 'IDX_PIM_FICHE_STATUS_UPDATED',
    columns: ['status', 'updated_at', 'id'],
),]
#[ORM\Index(name: 'IDX_PIM_FICHE_UPDATED', columns: ['updated_at', 'id'])]
#[ORM\Index(name: 'IDX_PIM_FICHE_MERGED_INTO', columns: ['merged_into_id'])]
#[ORM\Index(name: 'FTX_PIM_FICHE_LABEL', columns: ['label'], flags: ['fulltext'])]
#[ORM\HasLifecycleCallbacks]
class Fiche
{
    use TimestampableTrait;
    /** Transient guard used by technical/API updates that must preserve workflow state. */
    private int $workflowTransitionSuppressionDepth = 0;
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\Column(length: 32, enumType: TypeFiche::class)]
    private TypeFiche $type;
    // Null à la création : le trigger SQL l'attribue depuis le compteur,
    // sauf si un code a été fourni explicitement (import legacy).
    #[ORM\Column(
        options: ['unsigned' => true],
        updatable: false,
        generated: 'INSERT',
    ),]
    private ?int $code = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;
    #[ORM\Column(length: 32, enumType: StatutFiche::class)]
    private StatutFiche $status = StatutFiche::EnCours;
    #[ORM\Version]
    #[ORM\Column(
        type: Types::INTEGER,
        options: ['unsigned' => true, 'default' => 1],
    ),]
    private int $version = 1;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;
    /** Fiche survivante d'une fusion : posé quand cette fiche a été absorbée (statut Archivee, affichée « Fusionnée »). */
    #[ORM\Column(type: 'ulid', nullable: true)]
    private ?Ulid $mergedIntoId = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validationRequestedAt = null;
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $validationRequestedBy = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validationReviewedAt = null;
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $validationReviewedBy = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $validationFeedback = null;
    /** Adhérent Business Premium (interrupteur de la maquette, bloc « Statut et référencement »). */
    #[ORM\Column(name: 'business_premium', options: ['default' => false])]
    private bool $businessPremium = false;
    /**
     * Partenaire BP (icône partenaire de la marketplace, bp_produit.is_partenaire).
     * Distinct de businessPremium, qui pilote les relances de complétude.
     * Import legacy : colonne « Tag » vide → partenaire.
     */
    #[ORM\Column(name: 'partenaire_bp', options: ['default' => false])]
    private bool $partenaireBp = false;
    /** Contributeur interne responsable de la fiche (organisationnel, sans effet sur le workflow). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assignee_id', nullable: true, onDelete: 'SET NULL')]
    private ?\App\Account\Entity\User $assignee = null;
    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(
        name: 'localisation_id',
        referencedColumnName: 'id',
        nullable: true,
        unique: true,
        onDelete: 'SET NULL',
    ),]
    private ?Localisation $localisation = null;
    /** @var Collection<int, FicheAttributValeur> */
    #[ORM\OneToMany(
        mappedBy: 'fiche',
        targetEntity: FicheAttributValeur::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    ),]
    private Collection $attributValues;
    /** @var Collection<int, RessourceLieu> */
    #[ORM\OneToMany(
        mappedBy: 'fiche',
        targetEntity: RessourceLieu::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    ),]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $resources;
    /** @var Collection<int, FicheSiteDiffusion> */
    #[ORM\OneToMany(
        mappedBy: 'fiche',
        targetEntity: FicheSiteDiffusion::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    ),]
    private Collection $siteSelections;

    public function __construct(TypeFiche $type, ?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
        $this->type = $type;
        $this->attributValues = new ArrayCollection();
        $this->resources = new ArrayCollection();
        $this->siteSelections = new ArrayCollection();
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

    public function type(): TypeFiche
    {
        return $this->type;
    }

    public function code(): int
    {
        if (null === $this->code) {
            throw new \LogicException('Le code de la fiche sera attribué lors de son enregistrement.');
        }

        return $this->code;
    }

    /**
     * Fixe le code avant le premier enregistrement (reprise de données :
     * le code legacy est conservé). Sans appel, le trigger SQL attribue le
     * prochain code du compteur.
     */
    public function assignImportedCode(int $code): void
    {
        if (null !== $this->code) {
            throw new \LogicException('Le code d\'une fiche est immuable.');
        }
        if ($code < 1) {
            throw new \InvalidArgumentException('Le code d\'une fiche doit être strictement positif.');
        }

        $this->code = $code;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function status(): StatutFiche
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function validationRequestedAt(): ?\DateTimeImmutable
    {
        return $this->validationRequestedAt;
    }

    public function validationRequestedBy(): ?string
    {
        return $this->validationRequestedBy;
    }

    public function validationReviewedAt(): ?\DateTimeImmutable
    {
        return $this->validationReviewedAt;
    }

    public function validationReviewedBy(): ?string
    {
        return $this->validationReviewedBy;
    }

    public function validationFeedback(): ?string
    {
        return $this->validationFeedback;
    }

    public function localisation(): ?Localisation
    {
        return $this->localisation;
    }

    /** @return Collection<int, RessourceLieu> */
    public function resources(): Collection
    {
        return $this->resources;
    }

    public function addResource(RessourceLieu $resource): void
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
            $resource->attachToFiche($this);
            $this->markChanged();
        }
    }

    public function removeResource(RessourceLieu $resource): void
    {
        if ($this->resources->removeElement($resource)) {
            $this->markChanged();
        }
    }

    public function changeLabel(?string $label): void
    {
        $label = self::normalize($label);
        if ($label === $this->label) {
            return;
        }
        $this->label = $label;
        $this->markChanged();
    }

    public function assignee(): ?\App\Account\Entity\User
    {
        return $this->assignee;
    }

    /** Assignation organisationnelle : la fiche est mise à jour sans transition de workflow. */
    public function changeAssignee(?\App\Account\Entity\User $assignee): void
    {
        if ($assignee === $this->assignee) {
            return;
        }
        $this->assignee = $assignee;
        $this->touch();
    }

    public function businessPremium(): bool
    {
        return $this->businessPremium;
    }

    public function changeBusinessPremium(bool $businessPremium): void
    {
        if ($businessPremium === $this->businessPremium) {
            return;
        }
        $this->businessPremium = $businessPremium;
        $this->markChanged();
    }

    public function changeLocalisation(?Localisation $localisation): void
    {
        if ($localisation === $this->localisation) {
            return;
        }
        $this->localisation = $localisation;
        $this->markChanged();
    }

    public function partenaireBp(): bool
    {
        return $this->partenaireBp;
    }

    public function changePartenaireBp(bool $partenaireBp): void
    {
        if ($partenaireBp === $this->partenaireBp) {
            return;
        }
        $this->partenaireBp = $partenaireBp;
        $this->markChanged();
    }

    public function submitForValidation(string $actorId): void
    {
        if (StatutFiche::EnCours !== $this->status) {
            throw new \DomainException('Seule une fiche en cours peut être soumise à validation.');
        }
        $this->status = StatutFiche::EnAttenteValidation;
        $this->validationRequestedAt = new \DateTimeImmutable();
        $this->validationRequestedBy = $actorId;
        $this->validationReviewedAt = null;
        $this->validationReviewedBy = null;
        $this->validationFeedback = null;
        $this->touch();
    }

    public function validate(string $actorId): void
    {
        if (StatutFiche::EnAttenteValidation !== $this->status) {
            throw new \DomainException('Seule une fiche en attente peut être validée.');
        }
        $this->status = StatutFiche::Validee;
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->validationFeedback = null;
        $this->touch();
    }

    public function publish(): void
    {
        if (StatutFiche::Validee !== $this->status) {
            throw new \DomainException('Seule une fiche validée peut être publiée.');
        }
        $this->status = StatutFiche::Publiee;
        $this->publishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function rejectValidation(string $actorId, string $reason): void
    {
        $reason = trim($reason);
        if (StatutFiche::EnAttenteValidation !== $this->status) {
            throw new \DomainException('Seule une fiche en attente peut être refusée.');
        }
        if ('' === $reason) {
            throw new \DomainException('Le motif du refus est obligatoire.');
        }
        $this->status = StatutFiche::EnCours;
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->validationFeedback = $reason;
        $this->touch();
    }

    /**
     * Archivage depuis n'importe quel statut : la décision produit veut
     * qu'une fiche puisse être retirée de la circulation quel que soit son
     * état d'avancement. Le passage à Archivee la dépublie de fait des sites
     * de diffusion (retrait marketplace décidé en aval par IndexFicheHandler /
     * MarketplaceSyncScheduler ; une fiche non publiée n'était de toute façon
     * plus diffusée). Seule l'idempotence est gardée.
     */
    public function archive(string $actorId): void
    {
        if (StatutFiche::Archivee === $this->status) {
            throw new \DomainException('La fiche est déjà archivée.');
        }
        $this->status = StatutFiche::Archivee;
        $this->archivedAt = new \DateTimeImmutable();
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->touch();
    }

    public function mergedIntoId(): ?Ulid
    {
        return $this->mergedIntoId;
    }

    /**
     * Absorption par une fusion : même effet qu'un archivage (dépublication et
     * retrait des flux décidés en aval), la fiche survivante étant mémorisée
     * pour que l'interface affiche « Fusionnée » avec le lien. Le retour en
     * circulation passe par le désarchivage normal, qui efface la trace.
     */
    public function markMergedInto(Ulid $survivantId, string $actorId): void
    {
        if ($survivantId->equals($this->id)) {
            throw new \DomainException('Une fiche ne peut pas être fusionnée dans elle-même.');
        }
        if (StatutFiche::Archivee === $this->status) {
            throw new \DomainException('La fiche est déjà archivée.');
        }
        $this->status = StatutFiche::Archivee;
        $this->archivedAt = new \DateTimeImmutable();
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->mergedIntoId = $survivantId;
        $this->touch();
    }

    /**
     * Désarchivage : une fiche archivée n'est pas un cul-de-sac, elle revient
     * en cours pour être reprise puis renvoyée dans le workflow normal
     * (Soumettre → Valider → Publier).
     */
    public function unarchive(string $actorId): void
    {
        if (StatutFiche::Archivee !== $this->status) {
            throw new \DomainException('Seule une fiche archivée peut être désarchivée.');
        }
        $this->status = StatutFiche::EnCours;
        $this->archivedAt = null;
        $this->mergedIntoId = null;
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->touch();
    }

    /**
     * Republication d'une fiche archivée : retour direct en publiée, sans
     * repasser par la validation (décision produit). Les obligations photos
     * restent contrôlées par l'appelant / l'invariant de publication.
     */
    public function republish(string $actorId): void
    {
        if (StatutFiche::Archivee !== $this->status) {
            throw new \DomainException('Seule une fiche archivée peut être republiée.');
        }
        $this->status = StatutFiche::Publiee;
        $this->publishedAt = new \DateTimeImmutable();
        $this->archivedAt = null;
        $this->mergedIntoId = null;
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->touch();
    }

    /** Publication technique réservée aux imports et aux jeux de données internes. */
    public function publishForImport(): void
    {
        $this->status = StatutFiche::Publiee;
        $this->publishedAt = new \DateTimeImmutable();
        $this->archivedAt = null;
        $this->mergedIntoId = null;
        $this->validationFeedback = null;
        $this->validationReviewedAt = null;
        $this->validationReviewedBy = null;
        $this->touch();
    }

    /**
     * Dépublication technique : les obligations photos (minimum du type,
     * plancher à une photo) ne sont plus satisfaites, la fiche retourne en cours
     * jusqu'à remise en conformité puis republication par le circuit normal.
     */
    public function unpublishForInsufficientPhotos(): void
    {
        if (StatutFiche::Publiee !== $this->status) {
            throw new \DomainException('Seule une fiche publiée peut être dépubliée.');
        }
        $this->status = StatutFiche::EnCours;
        $this->validationFeedback = 'Dépublication automatique : les obligations photos ne sont plus satisfaites.';
        $this->touch();
    }

    /**
     * Dépublication confirmée par l'utilisateur : un champ obligatoire de la
     * bible (gamme Lieu) vient d'être vidé sur une fiche publiée. La fiche
     * retourne en cours jusqu'à remise en conformité puis resoumission.
     *
     * @param list<string> $champs Libellés des champs obligatoires désormais vides
     */
    public function unpublishForMissingRequiredFields(array $champs): void
    {
        if (StatutFiche::Publiee !== $this->status) {
            throw new \DomainException('Seule une fiche publiée peut être dépubliée.');
        }
        $this->status = StatutFiche::EnCours;
        $this->validationFeedback = 'Dépublication : champs obligatoires vidés — '.implode(', ', $champs).'.';
        $this->touch();
    }

    /** @return list<int> */
    public function valueIdsFor(string $attributeCode): array
    {
        return array_values(
            array_map(
                static fn (FicheAttributValeur $link): int => $link->valueId(),
                $this->attributValues
                    ->filter(
                        static fn (
                            FicheAttributValeur $link,
                        ): bool => $link->attributeCode() === $attributeCode,
                    )
                    ->toArray(),
            ),
        );
    }

    /** @param list<int> $valueIds */
    public function replaceAttributeValues(
        string $attributeCode,
        array $valueIds,
    ): void {
        $valueIds = array_values(array_unique($valueIds));
        $requested = array_fill_keys($valueIds, true);
        $current = [];
        $aRetirer = [];
        // Iterating initializes the persistent collection once; subsequent replacements
        // in the same request reuse that in-memory collection.
        foreach ($this->attributValues as $link) {
            if ($link->attributeCode() !== $attributeCode) {
                continue;
            }
            $current[$link->valueId()] = true;
            if (!isset($requested[$link->valueId()])) {
                $aRetirer[] = $link;
            }
        }
        $aAjouter = array_values(
            array_filter(
                $valueIds,
                static fn (int $valueId): bool => !isset($current[$valueId]),
            ),
        );
        // Sélection inchangée : un enregistrement sans modification ne doit
        // ni toucher la fiche ni la faire repasser en cours.
        if ([] === $aRetirer && [] === $aAjouter) {
            return;
        }
        foreach ($aRetirer as $link) {
            $this->attributValues->removeElement($link);
        }
        foreach ($aAjouter as $valueId) {
            $this->attributValues->add(
                new FicheAttributValeur($this, $attributeCode, $valueId),
            );
        }
        $this->markChanged();
    }

    /** @return list<int> */
    public function siteDiffusionIds(): array
    {
        return array_values(
            array_map(
                static fn (FicheSiteDiffusion $link): int => $link->siteId(),
                $this->siteSelections->toArray(),
            ),
        );
    }

    /** @param list<SiteDiffusion> $sites */
    public function replaceSiteDiffusion(array $sites): void
    {
        $requested = [];
        foreach ($sites as $site) {
            $requested[$site->id() ?? spl_object_id($site)] = $site;
        }
        $current = [];
        $aRetirer = [];
        foreach ($this->siteSelections as $link) {
            $current[$link->siteId()] = true;
            if (!isset($requested[$link->siteId()])) {
                $aRetirer[] = $link;
            }
        }
        $aAjouter = [];
        foreach ($requested as $siteId => $site) {
            if (!isset($current[$siteId])) {
                $aAjouter[] = $site;
            }
        }
        // Sélection inchangée : un enregistrement sans modification ne doit
        // ni toucher la fiche ni la faire repasser en cours.
        if ([] === $aRetirer && [] === $aAjouter) {
            return;
        }
        foreach ($aRetirer as $link) {
            $this->siteSelections->removeElement($link);
        }
        foreach ($aAjouter as $site) {
            $this->siteSelections->add(new FicheSiteDiffusion($this, $site));
        }
        $this->markChanged();
    }

    /**
     * Ajout de sites sans retrait ni transition de workflow : attribuer un
     * canal de diffusion supplémentaire ne remet pas la fiche en validation.
     *
     * @param list<SiteDiffusion> $sites
     *
     * @return int Nombre de sites réellement ajoutés
     */
    public function ajouterSitesDiffusion(array $sites): int
    {
        $presents = array_fill_keys($this->siteDiffusionIds(), true);
        $ajoutes = 0;
        foreach ($sites as $site) {
            $siteId = $site->id();
            if (null === $siteId || isset($presents[$siteId])) {
                continue;
            }
            $presents[$siteId] = true;
            $this->siteSelections->add(new FicheSiteDiffusion($this, $site));
            ++$ajoutes;
        }
        if ($ajoutes > 0) {
            $this->touch();
        }

        return $ajoutes;
    }

    public function markChanged(): void
    {
        if ($this->workflowTransitionSuppressionDepth > 0) {
            $this->touch();

            return;
        }
        if (StatutFiche::EnCours !== $this->status) {
            $this->status = StatutFiche::EnCours;
            $this->archivedAt = null;
            $this->mergedIntoId = null;
            $this->validationRequestedAt = null;
            $this->validationRequestedBy = null;
            $this->validationReviewedAt = null;
            $this->validationReviewedBy = null;
        }
        $this->touch();
    }

    /** Mise à jour technique sans modifier le cycle de validation. */
    public function markSystemChanged(): void
    {
        $this->touch();
    }

    /**
     * Exécute une modification en conservant le statut et toutes les métadonnées
     * du workflow. La version et la date de modification restent actualisées.
     *
     * @template T
     *
     * @param callable(): T $mutation
     *
     * @return T
     */
    public function preserveWorkflowDuring(callable $mutation): mixed
    {
        $workflow = [
            $this->status,
            $this->publishedAt,
            $this->archivedAt,
            $this->validationRequestedAt,
            $this->validationRequestedBy,
            $this->validationReviewedAt,
            $this->validationReviewedBy,
            $this->validationFeedback,
        ];
        ++$this->workflowTransitionSuppressionDepth;
        try {
            return $mutation();
        } finally {
            --$this->workflowTransitionSuppressionDepth;
            [
                $this->status,
                $this->publishedAt,
                $this->archivedAt,
                $this->validationRequestedAt,
                $this->validationRequestedBy,
                $this->validationReviewedAt,
                $this->validationReviewedBy,
                $this->validationFeedback,
            ] = $workflow;
        }
    }

    /**
     * Exécute une mutation (typiquement un flush) sous suppression de
     * transition de workflow pour plusieurs fiches à la fois — pour les mises
     * à jour techniques de fiches liées (resynchronisation d'une liaison
     * Lieu ↔ Restaurant) qui ne doivent pas repasser ces fiches « en cours ».
     *
     * @template T
     *
     * @param list<self>    $fiches
     * @param callable(): T $mutation
     *
     * @return T
     */
    public static function preserveWorkflowsDuring(array $fiches, callable $mutation): mixed
    {
        $fiche = array_shift($fiches);
        if (null === $fiche) {
            return $mutation();
        }

        return $fiche->preserveWorkflowDuring(static fn (): mixed => self::preserveWorkflowsDuring($fiches, $mutation));
    }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
