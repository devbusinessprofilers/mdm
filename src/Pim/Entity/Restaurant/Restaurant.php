<?php

declare(strict_types=1);

namespace App\Pim\Entity\Restaurant;

use App\Pim\Attribute\CompletenessTarget;
use App\Pim\Entity\CompletenessScoresTrait;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Validation\ValidRestaurant;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RestaurantRepository::class)]
#[ORM\Table(name: 'pim_restaurant')]
#[ORM\Index(name: 'IDX_RESTAURANT_COMPLETENESS_REVISION', columns: ['completeness_revision'])]
#[ORM\HasLifecycleCallbacks]
#[ValidRestaurant(groups: ['Draft'])]
#[ValidRestaurant(groups: ['Submission'])]
class Restaurant
{
    use TimestampableTrait {
        touch as touchDetail;
    }
    use CompletenessScoresTrait;

    public const WEBSITE_MAX_LENGTH = 100;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'fiche_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\Column(length: self::WEBSITE_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::WEBSITE_MAX_LENGTH)]
    private ?string $siteOfficiel = null;

    #[ORM\Column(nullable: true)]
    private ?bool $privatisationTotale = null;

    #[ORM\Column(nullable: true)]
    private ?bool $privatisationPartielle = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureOuverture = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureFermeture = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accesPmr = null;

    #[ORM\Column(nullable: true)]
    private ?bool $toilettesPmr = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionGenerale = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $atouts = [];

    #[ORM\Column(nullable: true)]
    private ?int $capaciteAssiseMax = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteEspacePrivatisable = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteBanquet = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteCocktail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;

    /** @var Collection<int, RestaurantPeriodeFermeture> */
    #[ORM\OneToMany(mappedBy: 'restaurant', targetEntity: RestaurantPeriodeFermeture::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateDebut' => 'ASC', 'id' => 'ASC'])]
    private Collection $periodesFermeture;

    /** @var Collection<int, RestaurantAcces> */
    #[ORM\OneToMany(mappedBy: 'restaurant', targetEntity: RestaurantAcces::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['type' => 'ASC', 'position' => 'ASC', 'id' => 'ASC'])]
    private Collection $acces;

    /** @var Collection<int, RestaurantSalle> */
    #[ORM\OneToMany(mappedBy: 'restaurant', targetEntity: RestaurantSalle::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $salles;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->fiche = new Fiche(TypeFiche::Restaurant, $this->id);
        $this->periodesFermeture = new ArrayCollection();
        $this->acces = new ArrayCollection();
        $this->salles = new ArrayCollection();
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

    public function code(): int
    {
        return $this->fiche->code();
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

    /** @return list<string> */
    public function typesRestaurant(): array
    {
        return $this->attributeValues('TYPE_RESTAURANT');
    }

    /** @param list<string> $values */
    public function changeTypesRestaurant(array $values): void
    {
        $this->replaceAttributeValues('TYPE_RESTAURANT', $values);
    }

    /** @return list<string> */
    public function typesCuisine(): array
    {
        return $this->attributeValues('TYPE_CUISINE');
    }

    /** @param list<string> $values */
    public function changeTypesCuisine(array $values): void
    {
        $this->replaceAttributeValues('TYPE_CUISINE', $values);
    }

    /** @return list<string> */
    public function specificitesAlimentaires(): array
    {
        return $this->attributeValues('SPECIFICITE_ALIMENTAIRE');
    }

    /** @param list<string> $values */
    public function changeSpecificitesAlimentaires(array $values): void
    {
        $this->replaceAttributeValues('SPECIFICITE_ALIMENTAIRE', $values);
    }

    /** @return list<string> */
    public function typesEvenement(): array
    {
        return $this->attributeValues('TYPE_EVENEMENT');
    }

    /** @param list<string> $values */
    public function changeTypesEvenement(array $values): void
    {
        $this->replaceAttributeValues('TYPE_EVENEMENT', $values);
    }

    /** @return list<string> */
    public function joursOuverture(): array
    {
        return $this->attributeValues('DISPO_JOUR_OUVERTURE');
    }

    /** @param list<string> $values */
    public function changeJoursOuverture(array $values): void
    {
        $this->replaceAttributeValues('DISPO_JOUR_OUVERTURE', $values);
    }

    /** @return list<string> */
    public function services(): array
    {
        return $this->attributeValues('SERVICE_RESTAURANT');
    }

    /** @param list<string> $values */
    public function changeServices(array $values): void
    {
        $this->replaceAttributeValues('SERVICE_RESTAURANT', $values);
    }

    /** @return list<string> */
    public function equipements(): array
    {
        return $this->attributeValues('EQUIPEMENT_RESTAURANT');
    }

    /** @param list<string> $values */
    public function changeEquipements(array $values): void
    {
        $this->replaceAttributeValues('EQUIPEMENT_RESTAURANT', $values);
    }

    /** @return list<string> */
    public function engagementsRse(): array
    {
        return $this->attributeValues('ENGAGEMENT_RSE_RESTAURANT');
    }

    /** @param list<string> $values */
    public function changeEngagementsRse(array $values): void
    {
        $this->replaceAttributeValues('ENGAGEMENT_RSE_RESTAURANT', $values);
    }

    public function siteOfficiel(): ?string
    {
        return $this->siteOfficiel;
    }

    public function privatisationTotale(): ?bool
    {
        return $this->privatisationTotale;
    }

    public function privatisationPartielle(): ?bool
    {
        return $this->privatisationPartielle;
    }

    public function heureOuverture(): ?\DateTimeImmutable
    {
        return $this->heureOuverture;
    }

    public function heureFermeture(): ?\DateTimeImmutable
    {
        return $this->heureFermeture;
    }

    public function accesPmr(): ?bool
    {
        return $this->accesPmr;
    }

    public function toilettesPmr(): ?bool
    {
        return $this->toilettesPmr;
    }

    public function descriptionGenerale(): ?string
    {
        return $this->descriptionGenerale;
    }

    /** @return list<string> */
    public function atouts(): array
    {
        return $this->atouts;
    }

    public function capaciteAssiseMax(): ?int
    {
        return $this->capaciteAssiseMax;
    }

    public function capaciteEspacePrivatisable(): ?int
    {
        return $this->capaciteEspacePrivatisable;
    }

    public function capaciteBanquet(): ?int
    {
        return $this->capaciteBanquet;
    }

    public function capaciteCocktail(): ?int
    {
        return $this->capaciteCocktail;
    }

    public function youtubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function changeSiteOfficiel(?string $value): void
    {
        $this->setString('siteOfficiel', $value);
    }

    public function changePrivatisationTotale(?bool $value): void
    {
        $this->set('privatisationTotale', $value);
    }

    public function changePrivatisationPartielle(?bool $value): void
    {
        $this->set('privatisationPartielle', $value);
    }

    public function changeHeureOuverture(?\DateTimeImmutable $value): void
    {
        $this->set('heureOuverture', $value);
    }

    public function changeHeureFermeture(?\DateTimeImmutable $value): void
    {
        $this->set('heureFermeture', $value);
    }

    public function changeAccesPmr(?bool $value): void
    {
        $this->set('accesPmr', $value);
    }

    public function changeToilettesPmr(?bool $value): void
    {
        $this->set('toilettesPmr', $value);
    }

    public function changeDescriptionGenerale(?string $value): void
    {
        $this->setString('descriptionGenerale', $value);
    }

    /** @param list<string> $value */
    public function changeAtouts(array $value): void
    {
        $this->set('atouts', self::normalizeList($value));
    }

    public function changeCapaciteAssiseMax(?int $value): void
    {
        $this->set('capaciteAssiseMax', $value);
    }

    public function changeCapaciteEspacePrivatisable(?int $value): void
    {
        $this->set('capaciteEspacePrivatisable', $value);
    }

    public function changeCapaciteBanquet(?int $value): void
    {
        $this->set('capaciteBanquet', $value);
    }

    public function changeCapaciteCocktail(?int $value): void
    {
        $this->set('capaciteCocktail', $value);
    }

    public function changeYoutubeUrl(?string $value): void
    {
        $this->setString('youtubeUrl', $value);
    }

    /** @return Collection<int, RestaurantPeriodeFermeture> */
    public function periodesFermeture(): Collection
    {
        return $this->periodesFermeture;
    }

    public function addPeriodeFermeture(RestaurantPeriodeFermeture $value): void
    {
        if (!$this->periodesFermeture->contains($value)) {
            $this->periodesFermeture->add($value);
            $value->attachTo($this);
            $this->touch();
        }
    }

    public function removePeriodeFermeture(RestaurantPeriodeFermeture $value): void
    {
        if ($this->periodesFermeture->removeElement($value)) {
            $value->detachFrom($this);
            $this->touch();
        }
    }

    /** @return Collection<int, RestaurantAcces> */
    public function acces(): Collection
    {
        return $this->acces;
    }

    public function addAcces(RestaurantAcces $value): void
    {
        if (!$this->acces->contains($value)) {
            $this->acces->add($value);
            $value->attachTo($this);
            $this->touch();
        }
    }

    public function removeAcces(RestaurantAcces $value): void
    {
        if ($this->acces->removeElement($value)) {
            $value->detachFrom($this);
            $this->touch();
        }
    }

    /** @return Collection<int, RestaurantSalle> */
    public function salles(): Collection
    {
        return $this->salles;
    }

    public function addSalle(RestaurantSalle $value): void
    {
        if (!$this->salles->contains($value)) {
            $this->salles->add($value);
            $value->attachTo($this);
            $this->touch();
        }
    }

    public function removeSalle(RestaurantSalle $value): void
    {
        if ($this->salles->removeElement($value)) {
            $value->detachFrom($this);
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
            throw new \DomainException('Une ressource Restaurant ne peut pas être rattachée à un Lieu.');
        }

        if (
            null !== $value->restaurantSalle()
            && $value->restaurantSalle()->restaurant() !== $this
        ) {
            throw new \DomainException(
                'La salle de la ressource doit appartenir au Restaurant.',
            );
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
    private function attributeValues(string $attribute): array
    {
        return array_map(
            static fn (int $id): string => RestaurantLovCatalog::valueCode($attribute, $id),
            $this->fiche->valueIdsFor($attribute),
        );
    }

    /** @param list<string> $values */
    private function replaceAttributeValues(string $attribute, array $values): void
    {
        $this->fiche->replaceAttributeValues(
            $attribute,
            RestaurantLovCatalog::valueIds($attribute, $values),
        );
        $this->touch();
    }

    private function set(string $property, mixed $value): void
    {
        $this->{$property} = $value;
        $this->touch();
    }

    private function setString(string $property, ?string $value): void
    {
        $value = null === $value ? '' : trim($value);
        $this->set($property, '' === $value ? null : $value);
    }

    public function markChanged(): void
    {
        $this->touch();
    }

    private function touch(): void
    {
        $this->touchDetail();
        $this->fiche->markChanged();
    }

    /** @param list<string> $values
     *  @return list<string>
     */
    private static function normalizeList(array $values): array
    {
        return array_values(
            array_unique(array_filter(array_map(trim(...), $values))),
        );
    }
}
