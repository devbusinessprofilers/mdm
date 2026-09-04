<?php

declare(strict_types=1);

namespace App\Pim\Entity\Restaurant;

use App\Pim\Attribute\CompletenessTarget;
use App\Pim\Entity\AvecHorairesJours;
use App\Pim\Entity\CompletenessScoresTrait;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\HorairesJours;
use App\Pim\Entity\Lieu\Lieu;
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
class Restaurant implements AvecHorairesJours
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

    // Lieu propriétaire du restaurant (liaison 1–1, éditable des deux fiches).
    #[ORM\OneToOne(inversedBy: 'restaurant', targetEntity: Lieu::class)]
    #[ORM\JoinColumn(name: 'lieu_id', nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Lieu $lieu = null;

    /** @var list<Fiche> Fiches liées dont le payload marketplace change (transitoire, drainé à l'enregistrement). */
    private array $fichesLieesAResynchroniser = [];

    #[ORM\Column(length: self::WEBSITE_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::WEBSITE_MAX_LENGTH)]
    private ?string $siteOfficiel = null;

    #[ORM\Column(nullable: true)]
    private ?bool $privatisationTotale = null;

    #[ORM\Column(nullable: true)]
    private ?bool $privatisationPartielle = null;

    // Horaires par jour — {jour: {ouverture: 'HH:MM', fermeture: 'HH:MM'}},
    // clés = codes LOV DISPO_JOUR_OUVERTURE de la gamme. L'amplitude globale
    // (contrat marketplace/Salesforce) est dérivée via HorairesJours::amplitude.
    /** @var array<string, array{ouverture: ?string, fermeture: ?string}>|null */
    #[ORM\Column(name: 'horaires_jours', type: Types::JSON, nullable: true)]
    private ?array $horairesJours = null;

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

    // Onglet Tarifs (maquette portail) : six montants HT « à partir de »,
    // null = prestation non proposée (interrupteur décoché).
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifDejeunerAssis = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifCocktailDejeunatoire = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifDinerAssis = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifCocktailDinatoire = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifForfaitVin = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tarifForfaitAlcool = null;

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

    /** Facturation & partenariat (maquette portail), porté par la fiche. */
    public function administratif(): FicheAdministratif
    {
        return $this->fiche->administratif();
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

    public function lieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function changeLieu(?Lieu $value): void
    {
        if ($value === $this->lieu) {
            return;
        }
        if (null !== $value && null !== $value->restaurant() && $value->restaurant() !== $this) {
            throw new \DomainException('Ce lieu est déjà associé à un autre restaurant.');
        }
        // Les fiches lieu détachée/attachée changent de payload marketplace
        // sans transition de workflow : mise à jour technique + resync.
        $ancien = $this->lieu;
        if (null !== $ancien) {
            $ancien->syncRestaurant(null);
            $this->trackFicheLiee($ancien->fiche());
        }
        if (null !== $value) {
            $value->syncRestaurant($this);
            $this->trackFicheLiee($value->fiche());
        }
        $this->lieu = $value;
        $this->touch();
    }

    /** @internal réservé à Lieu::changeRestaurant — côté propriétaire, sans transition de workflow. */
    public function syncLieu(?Lieu $value): void
    {
        $this->lieu = $value;
        $this->fiche->markSystemChanged();
    }

    /**
     * Fiches liées à réindexer, vidées à la lecture. Le flush qui suit
     * s'exécute sous Fiche::preserveWorkflowsDuring de ces fiches : leur
     * statut de workflow ne doit pas bouger pour une simple resynchronisation.
     *
     * @return list<Fiche>
     */
    public function drainFichesLieesAResynchroniser(): array
    {
        $fiches = $this->fichesLieesAResynchroniser;
        $this->fichesLieesAResynchroniser = [];

        return $fiches;
    }

    private function trackFicheLiee(Fiche $fiche): void
    {
        if (!in_array($fiche, $this->fichesLieesAResynchroniser, true)) {
            $this->fichesLieesAResynchroniser[] = $fiche;
        }
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

    /** @return array<string, array{ouverture: ?string, fermeture: ?string}>|null */
    public function horairesJours(): ?array
    {
        return $this->horairesJours;
    }

    /** Première ouverture de la semaine (amplitude dérivée — complétude, API). */
    public function amplitudeOuverture(): ?string
    {
        return HorairesJours::amplitude($this->horairesJours)['ouverture'];
    }

    /** Dernière fermeture de la semaine (amplitude dérivée — complétude, API). */
    public function amplitudeFermeture(): ?string
    {
        return HorairesJours::amplitude($this->horairesJours)['fermeture'];
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

    /** @param array<string, array{ouverture?: ?string, fermeture?: ?string}>|null $value */
    public function changeHorairesJours(?array $value): void
    {
        $this->set('horairesJours', HorairesJours::nettoie($value));
    }

    public function changeHoraireJour(array $valeur): void
    {
        $jours = $this->horairesJours ?? [];
        if (null === ($valeur['heures'] ?? null)) {
            unset($jours[$valeur['jour']]);
        } else {
            $jours[$valeur['jour']] = $valeur['heures'];
        }
        $this->changeHorairesJours($jours);
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

    public function tarifDejeunerAssis(): ?string
    {
        return $this->tarifDejeunerAssis;
    }

    public function changeTarifDejeunerAssis(?string $value): void
    {
        $this->setString('tarifDejeunerAssis', $value);
    }

    public function tarifCocktailDejeunatoire(): ?string
    {
        return $this->tarifCocktailDejeunatoire;
    }

    public function changeTarifCocktailDejeunatoire(?string $value): void
    {
        $this->setString('tarifCocktailDejeunatoire', $value);
    }

    public function tarifDinerAssis(): ?string
    {
        return $this->tarifDinerAssis;
    }

    public function changeTarifDinerAssis(?string $value): void
    {
        $this->setString('tarifDinerAssis', $value);
    }

    public function tarifCocktailDinatoire(): ?string
    {
        return $this->tarifCocktailDinatoire;
    }

    public function changeTarifCocktailDinatoire(?string $value): void
    {
        $this->setString('tarifCocktailDinatoire', $value);
    }

    public function tarifForfaitVin(): ?string
    {
        return $this->tarifForfaitVin;
    }

    public function changeTarifForfaitVin(?string $value): void
    {
        $this->setString('tarifForfaitVin', $value);
    }

    public function tarifForfaitAlcool(): ?string
    {
        return $this->tarifForfaitAlcool;
    }

    public function changeTarifForfaitAlcool(?string $value): void
    {
        $this->setString('tarifForfaitAlcool', $value);
    }

    /** @return array<string, ?string> Les six tarifs, indexés par nom de propriété (ordre maquette). */
    public function tarifs(): array
    {
        return [
            'tarifDejeunerAssis' => $this->tarifDejeunerAssis,
            'tarifCocktailDejeunatoire' => $this->tarifCocktailDejeunatoire,
            'tarifDinerAssis' => $this->tarifDinerAssis,
            'tarifCocktailDinatoire' => $this->tarifCocktailDinatoire,
            'tarifForfaitVin' => $this->tarifForfaitVin,
            'tarifForfaitAlcool' => $this->tarifForfaitAlcool,
        ];
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
            throw new \DomainException('La salle de la ressource doit appartenir au Restaurant.');
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

    /** Affecte la propriété et marque la fiche modifiée, seulement si la valeur change (même règle que les LOV). */
    private function set(string $property, mixed $value): void
    {
        $propriete = new \ReflectionProperty($this, $property);
        if ($propriete->isInitialized($this)) {
            $courante = $this->{$property};
            if ($courante === $value || (is_object($value) && is_object($courante) && $courante::class === $value::class && $courante == $value)) {
                return;
            }
        }
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
     * @return list<string>
     */
    private static function normalizeList(array $values): array
    {
        return array_values(
            array_unique(array_filter(array_map(trim(...), $values))),
        );
    }
}
