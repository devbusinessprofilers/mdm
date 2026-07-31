<?php

declare(strict_types=1);

namespace App\Pim\Entity\Activite;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Validation\ValidActivite;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'pim_activite')]
#[ORM\HasLifecycleCallbacks]
#[ValidActivite(groups: ['Draft'])]
#[ValidActivite(groups: ['Submission'])]
class Activite
{
    use TimestampableTrait {
        touch as touchDetail;
    }
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[
        ORM\JoinColumn(
            name: 'fiche_id',
            nullable: false,
            unique: true,
            onDelete: 'CASCADE',
        ),
    ]
    private Fiche $fiche;
    #[ORM\ManyToOne]
    #[
        ORM\JoinColumn(
            name: 'prestataire_value_id',
            nullable: true,
            onDelete: 'RESTRICT',
        ),
    ]
    private ?ValeurAttribut $prestataire = null;
    #[
        ORM\Column(
            length: 16,
            enumType: ModeInterventionActivite::class,
            nullable: true,
        ),
    ]
    private ?ModeInterventionActivite $modeIntervention = null;
    #[ORM\Column(options: ['default' => false])]
    private bool $touteFrance = false;
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $paysMobiles = [];
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $regionsMobiles = [];
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $departementsMobiles = [];
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionGenerale = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comprendPrestation = null;
    #[ORM\Column(nullable: true)]
    private ?int $participantsMin = null;
    #[ORM\Column(nullable: true)]
    private ?int $participantsMax = null;
    #[ORM\Column(nullable: true)]
    private ?int $dureeMinMinutes = null;
    #[ORM\Column(nullable: true)]
    private ?int $dureeMaxMinutes = null;
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $plus = [];
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifParPersonne = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;
    /** @var Collection<int, OffreActivite> */
    #[
        ORM\OneToMany(
            mappedBy: 'activite',
            targetEntity: OffreActivite::class,
            cascade: ['persist', 'remove'],
            orphanRemoval: true,
        ),
    ]
    #[ORM\OrderBy(['type' => 'ASC', 'position' => 'ASC'])]
    private Collection $offres;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->fiche = new Fiche(TypeFiche::Activite, $this->id);
        $this->offres = new ArrayCollection();
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function code(): ?int
    {
        return $this->fiche->code();
    }

    public function changeCode(?int $value): void
    {
        $this->fiche->changeCode($value);
    }

    public function label(): ?string
    {
        return $this->fiche->label();
    }

    public function changeLabel(?string $value): void
    {
        $this->fiche->changeLabel($value);
    }

    public function localisation(): ?Localisation
    {
        return $this->fiche->localisation();
    }

    public function changeLocalisation(?Localisation $value): void
    {
        $this->fiche->changeLocalisation($value);
        $this->touch();
    }

    public function prestataire(): ?ValeurAttribut
    {
        return $this->prestataire;
    }

    public function changePrestataire(?ValeurAttribut $value): void
    {
        if (null !== $value && 'PRESTATAIRE' !== $value->attribute()->code()) {
            throw new \DomainException('La valeur sélectionnée n’est pas un prestataire.');
        }
        $this->prestataire = $value;
        $this->touch();
    }

    public function modeIntervention(): ?ModeInterventionActivite
    {
        return $this->modeIntervention;
    }

    public function changeModeIntervention(
        ?ModeInterventionActivite $value,
    ): void {
        $this->modeIntervention = $value;
        $this->touch();
    }

    public function touteFrance(): bool
    {
        return $this->touteFrance;
    }

    public function changeTouteFrance(bool $value): void
    {
        $this->touteFrance = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function paysMobiles(): array
    {
        return $this->paysMobiles;
    }

    /** @param list<string> $value */
    public function changePaysMobiles(array $value): void
    {
        $this->paysMobiles = self::normalizeList($value);
        $this->touch();
    }

    /** @return list<string> */
    public function regionsMobiles(): array
    {
        return $this->regionsMobiles;
    }

    /** @param list<string> $value */
    public function changeRegionsMobiles(array $value): void
    {
        $this->regionsMobiles = self::normalizeList($value);
        $this->touch();
    }

    /** @return list<string> */
    public function departementsMobiles(): array
    {
        return $this->departementsMobiles;
    }

    /** @param list<string> $value */
    public function changeDepartementsMobiles(array $value): void
    {
        $this->departementsMobiles = self::normalizeList($value);
        $this->touch();
    }

    public function descriptionGenerale(): ?string
    {
        return $this->descriptionGenerale;
    }

    public function changeDescriptionGenerale(?string $value): void
    {
        $this->descriptionGenerale = self::normalize($value);
        $this->touch();
    }

    public function comprendPrestation(): ?string
    {
        return $this->comprendPrestation;
    }

    public function changeComprendPrestation(?string $value): void
    {
        $this->comprendPrestation = self::normalize($value);
        $this->touch();
    }

    public function participantsMin(): ?int
    {
        return $this->participantsMin;
    }

    public function changeParticipantsMin(?int $value): void
    {
        $this->participantsMin = $value;
        $this->touch();
    }

    public function participantsMax(): ?int
    {
        return $this->participantsMax;
    }

    public function changeParticipantsMax(?int $value): void
    {
        $this->participantsMax = $value;
        $this->touch();
    }

    public function dureeMinMinutes(): ?int
    {
        return $this->dureeMinMinutes;
    }

    public function changeDureeMinMinutes(?int $value): void
    {
        $this->dureeMinMinutes = $value;
        $this->touch();
    }

    public function dureeMaxMinutes(): ?int
    {
        return $this->dureeMaxMinutes;
    }

    public function changeDureeMaxMinutes(?int $value): void
    {
        $this->dureeMaxMinutes = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function plus(): array
    {
        return $this->plus;
    }

    /** @param list<string> $value */
    public function changePlus(array $value): void
    {
        $this->plus = array_slice(self::normalizeList($value), 0, 4);
        $this->touch();
    }

    public function tarifParPersonne(): ?string
    {
        return $this->tarifParPersonne;
    }

    public function changeTarifParPersonne(?string $value): void
    {
        $this->tarifParPersonne = self::normalize($value);
        $this->touch();
    }

    public function youtubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function changeYoutubeUrl(?string $value): void
    {
        $this->youtubeUrl = self::normalize($value);
        $this->touch();
    }

    /** @return Collection<int, OffreActivite> */
    public function offres(): Collection
    {
        return $this->offres;
    }

    public function addOffre(OffreActivite $value): void
    {
        if (!$this->offres->contains($value)) {
            $this->offres->add($value);
            $value->attachTo($this);
            $this->touch();
        }
    }

    public function removeOffre(OffreActivite $value): void
    {
        if ($this->offres->removeElement($value)) {
            $this->touch();
        }
    }

    /** @return Collection<int, RessourceLieu> */
    public function ressources(): Collection
    {
        return $this->fiche->resources();
    }

    public function addRessource(RessourceLieu $value): void
    {
        if (null !== $value->lieu() || null !== $value->salle()) {
            throw new \DomainException('Une ressource Activité ne peut pas être rattachée à un Lieu ou une salle.');
        }
        $this->fiche->addResource($value);
        $this->touch();
    }

    public function removeRessource(RessourceLieu $value): void
    {
        $this->fiche->removeResource($value);
        $this->touch();
    }

    /** @return list<string> */
    public function types(): array
    {
        return $this->lovValues('TYPE_EXT_INT');
    }

    /** @param list<string> $value */
    public function changeTypes(array $value): void
    {
        $this->replaceLov('TYPE_EXT_INT', $value);
    }

    public function thematique(): ?string
    {
        return $this->lovValues('THEMATIQUE_ACTIVITE')[0] ?? null;
    }

    public function changeThematique(?string $value): void
    {
        $this->replaceLov(
            'THEMATIQUE_ACTIVITE',
            null === $value || '' === $value ? [] : [$value],
        );
    }

    /** @return list<string> */
    public function langues(): array
    {
        return $this->lovValues('LANGUE_PARLEE');
    }

    /** @param list<string> $value */
    public function changeLangues(array $value): void
    {
        $this->replaceLov('LANGUE_PARLEE', $value);
    }

    /** @return list<string> */
    public function engagementsRse(): array
    {
        return $this->lovValues('ENGAGEMENT_RSE');
    }

    /** @param list<string> $value */
    public function changeEngagementsRse(array $value): void
    {
        $this->replaceLov('ENGAGEMENT_RSE', $value);
    }

    /** @return list<string> */
    public function objectifs(): array
    {
        return $this->lovValues('OBJECTIF_SEMINAIRE');
    }

    /** @param list<string> $value */
    public function changeObjectifs(array $value): void
    {
        $this->replaceLov('OBJECTIF_SEMINAIRE', $value);
    }

    /** @return list<string> */
    private function lovValues(string $code): array
    {
        return array_map(
            static fn (int $id): string => ActiviteLovCatalog::valueCode(
                $code,
                $id,
            ),
            $this->fiche->valueIdsFor($code),
        );
    }

    /** @param list<string> $values */
    private function replaceLov(string $code, array $values): void
    {
        $this->fiche->replaceAttributeValues(
            $code,
            ActiviteLovCatalog::valueIds($code, $values),
        );
        $this->touch();
    }

    private function touch(): void
    {
        $this->touchDetail();
        $this->fiche->markChanged();
    }

    private static function normalize(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function normalizeList(array $values): array
    {
        /** @var list<string> $normalized */
        $normalized = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (string $v): string => trim($v),
                        $values,
                    ),
                    static fn (string $v): bool => '' !== $v,
                ),
            ),
        );

        return $normalized;
    }
}
