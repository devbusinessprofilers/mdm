<?php

declare(strict_types=1);

namespace App\Pim\Entity\Service;

use App\Pim\Entity\CompletenessScoresTrait;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Pim\Validation\ValidServiceEvenementiel;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ServiceEvenementielRepository::class)]
#[ORM\Table(name: "pim_service_evenementiel")]
#[ORM\Index(name: "IDX_SERVICE_EVENEMENTIEL_COMPLETENESS_REVISION", columns: ["completeness_revision"])]
#[ORM\HasLifecycleCallbacks]
#[ValidServiceEvenementiel(groups: ["Draft"])]
#[ValidServiceEvenementiel(groups: ["Submission"])]
class ServiceEvenementiel
{
    use TimestampableTrait {
        touch as touchDetail;
    }
    use CompletenessScoresTrait;

    #[ORM\Id]
    #[ORM\Column(type: "ulid", unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(cascade: ["persist", "remove"], orphanRemoval: true)]
    #[
        ORM\JoinColumn(
            name: "fiche_id",
            nullable: false,
            unique: true,
            onDelete: "CASCADE",
        ),
    ]
    private Fiche $fiche;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionGenerale = null;

    #[ORM\Column(nullable: true)]
    private ?bool $prestataireEsat = null;

    #[ORM\Column(nullable: true)]
    private ?bool $demarcheRse = null;

    #[ORM\Column(nullable: true)]
    private ?bool $adapteFemmesEnceintes = null;

    #[ORM\Column(nullable: true)]
    private ?bool $adapteMalentendants = null;

    #[ORM\Column(nullable: true)]
    private ?bool $adapteMalvoyants = null;

    #[ORM\Column(nullable: true)]
    private ?bool $materielInclus = null;

    #[ORM\Column(nullable: true)]
    private ?bool $equipementParticipantsRequis = null;

    #[ORM\Column(nullable: true)]
    private ?bool $equipementReceptionRequis = null;

    #[ORM\Column(nullable: true)]
    private ?bool $contraintesLogistiques = null;

    #[
        ORM\Column(
            length: 16,
            enumType: ModeInterventionService::class,
            nullable: true,
        ),
    ]
    private ?ModeInterventionService $modeIntervention = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $paysMobiles = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $regionsMobiles = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $departementsMobiles = [];

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tarifParPrestation = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tarifParPersonne = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tarifParJour = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tarifParDemiJournee = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tarifParHeure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $surDevis = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->fiche = new Fiche(TypeFiche::ServiceEvenementiel, $this->id);
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
    public function prestations(): array
    {
        return array_map(
            ServiceLovCatalog::valueCode(...),
            $this->fiche->valueIdsFor("TYPE_PRESTATAIRE"),
        );
    }

    /** @param list<string> $values */
    public function changePrestations(array $values): void
    {
        $this->fiche->replaceAttributeValues(
            "TYPE_PRESTATAIRE",
            ServiceLovCatalog::valueIds($values),
        );
        $this->touch();
    }

    public function descriptionGenerale(): ?string
    {
        return $this->descriptionGenerale;
    }

    public function changeDescriptionGenerale(?string $value): void
    {
        $this->setString("descriptionGenerale", $value);
    }

    public function prestataireEsat(): ?bool
    {
        return $this->prestataireEsat;
    }

    public function changePrestataireEsat(?bool $value): void
    {
        $this->set("prestataireEsat", $value);
    }

    public function demarcheRse(): ?bool
    {
        return $this->demarcheRse;
    }

    public function changeDemarcheRse(?bool $value): void
    {
        $this->set("demarcheRse", $value);
    }

    public function adapteFemmesEnceintes(): ?bool
    {
        return $this->adapteFemmesEnceintes;
    }

    public function changeAdapteFemmesEnceintes(?bool $value): void
    {
        $this->set("adapteFemmesEnceintes", $value);
    }

    public function adapteMalentendants(): ?bool
    {
        return $this->adapteMalentendants;
    }

    public function changeAdapteMalentendants(?bool $value): void
    {
        $this->set("adapteMalentendants", $value);
    }

    public function adapteMalvoyants(): ?bool
    {
        return $this->adapteMalvoyants;
    }

    public function changeAdapteMalvoyants(?bool $value): void
    {
        $this->set("adapteMalvoyants", $value);
    }

    public function materielInclus(): ?bool
    {
        return $this->materielInclus;
    }

    public function changeMaterielInclus(?bool $value): void
    {
        $this->set("materielInclus", $value);
    }

    public function equipementParticipantsRequis(): ?bool
    {
        return $this->equipementParticipantsRequis;
    }

    public function changeEquipementParticipantsRequis(?bool $value): void
    {
        $this->set("equipementParticipantsRequis", $value);
    }

    public function equipementReceptionRequis(): ?bool
    {
        return $this->equipementReceptionRequis;
    }

    public function changeEquipementReceptionRequis(?bool $value): void
    {
        $this->set("equipementReceptionRequis", $value);
    }

    public function contraintesLogistiques(): ?bool
    {
        return $this->contraintesLogistiques;
    }

    public function changeContraintesLogistiques(?bool $value): void
    {
        $this->set("contraintesLogistiques", $value);
    }

    public function modeIntervention(): ?ModeInterventionService
    {
        return $this->modeIntervention;
    }

    public function changeModeIntervention(
        ?ModeInterventionService $value,
    ): void {
        $this->set("modeIntervention", $value);
    }

    /** @return list<string> */
    public function paysMobiles(): array
    {
        return $this->paysMobiles;
    }

    /** @param list<string> $value */
    public function changePaysMobiles(array $value): void
    {
        $this->set("paysMobiles", self::normalizeList($value));
    }

    /** @return list<string> */
    public function regionsMobiles(): array
    {
        return $this->regionsMobiles;
    }

    /** @param list<string> $value */
    public function changeRegionsMobiles(array $value): void
    {
        $this->set("regionsMobiles", self::normalizeList($value));
    }

    /** @return list<string> */
    public function departementsMobiles(): array
    {
        return $this->departementsMobiles;
    }

    /** @param list<string> $value */
    public function changeDepartementsMobiles(array $value): void
    {
        $this->set("departementsMobiles", self::normalizeList($value));
    }

    public function tarifParPrestation(): ?float
    {
        return $this->tarifParPrestation;
    }

    public function changeTarifParPrestation(?float $value): void
    {
        $this->set("tarifParPrestation", $value);
    }

    public function tarifParPersonne(): ?float
    {
        return $this->tarifParPersonne;
    }

    public function changeTarifParPersonne(?float $value): void
    {
        $this->set("tarifParPersonne", $value);
    }

    public function tarifParJour(): ?float
    {
        return $this->tarifParJour;
    }

    public function changeTarifParJour(?float $value): void
    {
        $this->set("tarifParJour", $value);
    }

    public function tarifParDemiJournee(): ?float
    {
        return $this->tarifParDemiJournee;
    }

    public function changeTarifParDemiJournee(?float $value): void
    {
        $this->set("tarifParDemiJournee", $value);
    }

    public function tarifParHeure(): ?float
    {
        return $this->tarifParHeure;
    }

    public function changeTarifParHeure(?float $value): void
    {
        $this->set("tarifParHeure", $value);
    }

    public function surDevis(): ?bool
    {
        return $this->surDevis;
    }

    public function changeSurDevis(?bool $value): void
    {
        $this->set("surDevis", $value);
    }

    public function youtubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function changeYoutubeUrl(?string $value): void
    {
        $this->setString("youtubeUrl", $value);
    }

    /** @return Collection<int, RessourceLieu> */
    public function ressources(): Collection
    {
        return $this->fiche->resources();
    }

    public function addRessource(RessourceLieu $value): void
    {
        if (null !== $value->lieu() || null !== $value->salle()) {
            throw new \DomainException(
                "Une ressource Service ne peut pas être rattachée à un Lieu ou une salle.",
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

    private function set(string $property, mixed $value): void
    {
        $this->{$property} = $value;
        $this->touch();
    }

    private function setString(string $property, ?string $value): void
    {
        $value = null === $value ? "" : trim($value);
        $this->set($property, "" === $value ? null : $value);
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
