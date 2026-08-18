<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use App\Pim\Attribute\CompletenessTarget;
use App\Pim\Entity\CompletenessScoresTrait;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Repository\LieuRepository;
use App\Pim\Enum\TypeFiche;
use App\Shared\Entity\TimestampableTrait;
use App\Pim\Validation\ValidLieu;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: LieuRepository::class)]
#[ORM\Table(name: 'pim_lieu')]
#[ORM\Index(name: 'IDX_LIEU_COMPLETENESS_REVISION', columns: ['completeness_revision'])]
#[ORM\HasLifecycleCallbacks]
#[ValidLieu(groups: ['Draft'])]
#[ValidLieu(groups: ['Submission'])]
class Lieu
{
    use TimestampableTrait { touch as touchDetail; }
    use CompletenessScoresTrait;

    public const LABEL_MAX_LENGTH = 69;
    public const WEBSITE_MAX_LENGTH = 100;
    public const PMR_DETAILS_MAX_LENGTH = 150;
    public const DESCRIPTION_MAX_LENGTH = 1000;
    public const ATOUT_MAX_LENGTH = 35;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'fiche_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\OneToOne(mappedBy: 'lieu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private LieuAdministratif $administratif;

    #[ORM\OneToOne(mappedBy: 'lieu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private LieuTarification $tarification;

    /** @var Collection<int, Salle> */
    #[ORM\OneToMany(mappedBy: 'lieu', targetEntity: Salle::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'nom' => 'ASC'])]
    private Collection $salles;

    /** @var Collection<int, PeriodeFermeture> */
    #[ORM\OneToMany(mappedBy: 'lieu', targetEntity: PeriodeFermeture::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateDebut' => 'ASC'])]
    private Collection $periodesFermeture;

    /** @var Collection<int, AccesLieu> */
    #[ORM\OneToMany(mappedBy: 'lieu', targetEntity: AccesLieu::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['type' => 'ASC', 'position' => 'ASC'])]
    private Collection $acces;

    /** @var Collection<int, RessourceLieu> */
    #[ORM\OneToMany(mappedBy: 'lieu', targetEntity: RessourceLieu::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $ressources;

    // Typologie (Bible row 5)

    // Groupe et Chaîne hôtelière (Bible row 6)

    // ERP (Bible row 7)
    #[ORM\Column(name: 'generale_etab_rp', options: ['default' => false])]
    private bool $generaleEtabRp = false;

    // Site web officiel (Bible row 8)
    #[ORM\Column(name: 'generale_website_url', length: self::WEBSITE_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::WEBSITE_MAX_LENGTH)]
    private ?string $generaleWebsiteUrl = null;

    // Événements de prédilection (Bible row 9)
    #[ORM\Column(name: 'generale_gamme', length: 255, nullable: true)]
    private ?string $generaleGamme = null;

    // Gamme (Bible row 10)
    #[ORM\Column(name: 'generale_gamme_libelle', length: 255, nullable: true)]
    private ?string $generaleGammeLibelle = null;

    // Lieu privatisable (Bible row 11)
    #[ORM\Column(name: 'dispo_lieu_privatisable', options: ['default' => false])]
    private bool $dispoLieuPrivatisable = false;

    // Jours d'ouverture (Bible row 12)
    #[ORM\Column(name: 'dispo_jour_ouverture', length: 255, nullable: true)]
    private ?string $dispoJourOuverture = null;

    // Ouverture heure (Bible row 13)
    #[ORM\Column(name: 'dispo_heure_ouverture_heure', nullable: true)]
    private ?int $dispoHeureOuvertureHeure = null;

    // Ouverture minutes (Bible row 14)
    #[ORM\Column(name: 'dispo_heure_ouverture_minutes', nullable: true)]
    private ?int $dispoHeureOuvertureMinutes = null;

    // Fermeture heure (Bible row 15)
    #[ORM\Column(name: 'dispo_heure_fermeture_heure', nullable: true)]
    private ?int $dispoHeureFermetureHeure = null;

    // Fermeture minutes (Bible row 16)
    #[ORM\Column(name: 'dispo_heure_fermeture_minutes', nullable: true)]
    private ?int $dispoHeureFermetureMinutes = null;

    // Horaires par jour (éditeur maquette) — {jour: {ouverture: 'HH:MM', fermeture: 'HH:MM'}}.
    // Les quatre champs globaux ci-dessus restent le contrat marketplace : ils
    // suivent l'amplitude (première ouverture, dernière fermeture).
    /** @var array<string, array{ouverture: ?string, fermeture: ?string}>|null */
    #[ORM\Column(name: 'dispo_horaires_jours', type: Types::JSON, nullable: true)]
    private ?array $dispoHorairesJours = null;

    // Accès PMR (Bible row 35)
    #[ORM\Column(name: 'pmr_acces', options: ['default' => false])]
    private bool $pmrAcces = false;

    // Détails des accès PMR  (Bible row 36)
    #[ORM\Column(name: 'pmr_details', length: self::PMR_DETAILS_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::PMR_DETAILS_MAX_LENGTH)]
    private ?string $pmrDetails = null;

    // Thématique (Bible row 37)

    // Cadre / Environement (Bible row 38)

    // Ambiance (Bible row 39)

    // Texte de description (Bible row 40)
    #[ORM\Column(name: 'desc_generale', length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::DESCRIPTION_MAX_LENGTH)]
    private ?string $descGenerale = null;

    // Points d'intérêt à moins de 15 minutes à pied (Bible row 34)
    #[ORM\Column(name: 'desc_generale_point_interet', type: Types::TEXT, nullable: true)]
    private ?string $descGeneralePointInteret = null;

    // Plus n°1 (Bible row 41)
    #[ORM\Column(name: 'atout_1', length: self::ATOUT_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::ATOUT_MAX_LENGTH)]
    private ?string $atout1 = null;

    // Plus n°2 (Bible row 42)
    #[ORM\Column(name: 'atout_2', length: self::ATOUT_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::ATOUT_MAX_LENGTH)]
    private ?string $atout2 = null;

    // Plus n°3 (Bible row 43)
    #[ORM\Column(name: 'atout_3', length: self::ATOUT_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::ATOUT_MAX_LENGTH)]
    private ?string $atout3 = null;

    // Plus n°4 (Bible row 44)
    #[ORM\Column(name: 'atout_4', length: self::ATOUT_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::ATOUT_MAX_LENGTH)]
    private ?string $atout4 = null;

    // Plus n°5 (Bible row 45)
    #[ORM\Column(name: 'atout_5', length: self::ATOUT_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::ATOUT_MAX_LENGTH)]
    private ?string $atout5 = null;

    // Mon établissement dispose d'hébergement ? (Bible row 46)
    #[ORM\Column(name: 'chambre_hebergement', options: ['default' => true])]
    private bool $chambreHebergement = true;

    // Nombre total de chambres (Bible row 47)
    #[ORM\Column(name: 'chambre_nb_total', nullable: true)]
    private ?int $chambreNbTotal = null;

    // Dont nombre de chambres single / individuelle (Bible row 48)
    #[ORM\Column(name: 'chambre_nb_total_single', nullable: true)]
    private ?int $chambreNbTotalSingle = null;

    // Dont nombre de chambres twin (Bible row 49)
    #[ORM\Column(name: 'chambre_nb_total_twin', nullable: true)]
    private ?int $chambreNbTotalTwin = null;

    // Dont nombre de chambres double (Bible row 50)
    #[ORM\Column(name: 'chambre_double', nullable: true)]
    private ?int $chambreDouble = null;

    // Capacité d'accueil totale des personnes hébergées (Bible row 51)
    #[ORM\Column(name: 'chambre_capacite_totale', nullable: true)]
    private ?int $chambreCapaciteTotale = null;

    // Texte de description générale (Bible row 52)
    #[ORM\Column(name: 'chambre_desc_generale', length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    #[CompletenessTarget(self::DESCRIPTION_MAX_LENGTH)]
    private ?string $chambreDescGenerale = null;

    // Equipements (Bible row 53)

    // Mon établissement dispose de salles de réunion ? (Bible row 54)
    #[ORM\Column(name: 'salle_reunion_exist', options: ['default' => true])]
    private bool $salleReunionExist = true;

    // Nombre de salles de réunion (Bible row 55)
    #[ORM\Column(name: 'salle_reunion_nb_total', nullable: true)]
    private ?int $salleReunionNbTotal = null;

    // Capacité de la plus grande salle en configuration cocktail (Bible row 56)
    #[ORM\Column(name: 'salle_reunion_capacite_max_cocktail', nullable: true)]
    private ?int $salleReunionCapaciteMaxCocktail = null;

    // Capacité de la plus grande salle en configuration théâtre (Bible row 57)
    #[ORM\Column(name: 'salle_reunion_capacite_max_theatre', nullable: true)]
    private ?int $salleReunionCapaciteMaxTheatre = null;

    // Capacité de la plus petite salle en configuration théâtre (Bible row 58)
    #[ORM\Column(name: 'salle_reunion_capacite_min_theatre', nullable: true)]
    private ?int $salleReunionCapaciteMinTheatre = null;

    // Surface de la plus petite salle de réunion (en m²) (Bible row 59)
    #[ORM\Column(name: 'salle_reunion_surface_min_reunion', nullable: true)]
    private ?int $salleReunionSurfaceMinReunion = null;

    // Surface de la plus grande salle de réunion (en m²) (Bible row 60)
    #[ORM\Column(name: 'salle_reunion_surface_max_reunion', nullable: true)]
    private ?int $salleReunionSurfaceMaxReunion = null;

    // Texte de description (Bible row 61)
    #[ORM\Column(name: 'salle_reunion_desc_salle_seminaire', type: Types::TEXT, nullable: true)]
    private ?string $salleReunionDescSalleSeminaire = null;

    // Services (Bible row 78)

    // Technique & Réunion (Bible row 79)

    // Installation (Bible row 80)

    // Bien-être (Bible row 81)

    // Achats responsables (Bible row 83)

    // Descriptif complet des engagements RSE (Bible row 82)
    #[ORM\Column(name: 'rse_desc_generale', type: Types::TEXT, nullable: true)]
    private ?string $rseDescGenerale = null;

    // Démarche RSE — case à cocher (reprise du thème legacy « RSE »)
    #[ORM\Column(name: 'demarche_rse', options: ['default' => false])]
    private bool $demarcheRse = false;

    // Impact environnemental (Bible row 84)

    // Impact social (Bible row 85)

    // % de volume d'achat par catégorie ESAT STPA (Bible row 86)

    // Mobilité (Bible row 87)

    // Certification (Bible row 88)

    // Loisirs interne à l’établissement (Bible row 89)
    /** @var list<string> */
    #[ORM\Column(name: 'loisir_interne', type: Types::JSON)]
    private array $loisirInterne = [];

    // Nom du prestataire externe 1 (Bible row 90)
    /** @var list<string> */
    #[ORM\Column(name: 'loisir_externe_nom_presta', type: Types::JSON)]
    private array $loisirExterneNomPresta = [];

    // Nom activité 1 (Bible row 91)
    /** @var list<string> */
    #[ORM\Column(name: 'loisir_externe_nom_activite', type: Types::JSON)]
    private array $loisirExterneNomActivite = [];

    // Nombre de restaurants sur place (Bible row 93)
    #[ORM\Column(name: 'restaurant_total', nullable: true)]
    private ?int $restaurantTotal = null;

    // Nombre de salles de restauration (Bible row 94)
    #[ORM\Column(name: 'restaurant_salle_restauration', nullable: true)]
    private ?int $restaurantSalleRestauration = null;

    // Capacité maximale en configuration debout (Bible row 95)
    #[ORM\Column(name: 'restaurant_capacite_debout', nullable: true)]
    private ?int $restaurantCapaciteDebout = null;

    // Capacité maximale en configuration assise (Bible row 96)
    #[ORM\Column(name: 'restaurant_capacite_assis', nullable: true)]
    private ?int $restaurantCapaciteAssis = null;

    // Soirée dansante (Bible row 97)
    #[ORM\Column(name: 'restaurant_soiree_dansante', nullable: true)]
    private ?bool $restaurantSoireeDansante = null;

    // Cocktail dînatoire (Bible row 98)
    #[ORM\Column(name: 'restaurant_cocktail_dinatoire', nullable: true)]
    private ?bool $restaurantCocktailDinatoire = null;

    // Traiteur sur place (Bible row 99)
    #[ORM\Column(name: 'restaurant_traiteur_sur_place', nullable: true)]
    private ?bool $restaurantTraiteurSurPlace = null;

    // Gestion de l'intervention d'un traiteur externe (Bible row 100)
    #[ORM\Column(name: 'restaurant_intervention_traiteur_externe', nullable: true)]
    private ?bool $restaurantInterventionTraiteurExterne = null;

    // Traiteur externe autorisé ? (Bible row 101)
    #[ORM\Column(name: 'restaurant_trateur_externe_client', nullable: true)]
    private ?bool $restaurantTrateurExterneClient = null;

    // Restaurant privatisable (Bible row 102)
    #[ORM\Column(name: 'restaurant_privatisable', nullable: true)]
    private ?bool $restaurantPrivatisable = null;

    // Heure limite de diffusion de la musique (Bible row 103)
    #[ORM\Column(name: 'restaurant_heure_interruption_musique', length: 255, nullable: true)]
    private ?string $restaurantHeureInterruptionMusique = null;

    // Type de cuisine (Bible row 104)

    // Service(s) (Bible row 105)

    // Lien Youtube (Bible row 109)
    #[ORM\Column(name: 'generale_youtube', length: 255, nullable: true)]
    private ?string $generaleYoutube = null;

    // Administrative and pricing fields are stored in dedicated one-to-one blocks.

    // Statut Premium (Bible row 180)
    #[ORM\Column(name: 'mice_statut', length: 255, nullable: true)]
    private ?string $miceStatut = null;

    // Afficher contact (Bible row 181)
    #[ORM\Column(name: 'afficher_contact', options: ['default' => false])]
    private bool $afficherContact = false;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->fiche = new Fiche(TypeFiche::Lieu, $this->id);
        $this->administratif = new LieuAdministratif($this);
        $this->tarification = new LieuTarification($this);
        $this->salles = new ArrayCollection();
        $this->periodesFermeture = new ArrayCollection();
        $this->acces = new ArrayCollection();
        $this->ressources = new ArrayCollection();
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

    public function administratif(): LieuAdministratif
    {
        return $this->administratif;
    }

    public function tarification(): LieuTarification
    {
        return $this->tarification;
    }

    public function localisation(): ?Localisation
    {
        return $this->fiche->localisation();
    }

    public function changeLocalisation(?Localisation $localisation): void
    {
        $this->fiche->changeLocalisation($localisation);
        $this->touch();
    }

    /** @return Collection<int, Salle> */
    public function salles(): Collection
    {
        return $this->salles;
    }

    public function addSalle(Salle $salle): void
    {
        if ($this->salles->contains($salle)) {
            return;
        }

        $this->salles->add($salle);
        $salle->attachTo($this);
        $this->touch();
    }

    public function removeSalle(Salle $salle): void
    {
        if ($this->salles->removeElement($salle)) {
            $salle->detachFrom($this);
            $this->touch();
        }
    }

    /** @return Collection<int, PeriodeFermeture> */
    public function periodesFermeture(): Collection
    {
        return $this->periodesFermeture;
    }

    public function addPeriodeFermeture(PeriodeFermeture $periode): void
    {
        if ($this->periodesFermeture->contains($periode)) {
            return;
        }

        $this->periodesFermeture->add($periode);
        $periode->attachTo($this);
        $this->touch();
    }

    public function removePeriodeFermeture(PeriodeFermeture $periode): void
    {
        if ($this->periodesFermeture->removeElement($periode)) {
            $periode->detachFrom($this);
            $this->touch();
        }
    }

    /** @return Collection<int, AccesLieu> */
    public function acces(): Collection
    {
        return $this->acces;
    }

    public function addAcces(AccesLieu $acces): void
    {
        if ($this->acces->contains($acces)) {
            return;
        }

        $this->acces->add($acces);
        $acces->attachTo($this);
        $this->touch();
    }

    public function removeAcces(AccesLieu $acces): void
    {
        if ($this->acces->removeElement($acces)) {
            $acces->detachFrom($this);
            $this->touch();
        }
    }

    /** @return Collection<int, RessourceLieu> */
    public function ressources(): Collection
    {
        return $this->ressources;
    }

    public function addRessource(RessourceLieu $ressource): void
    {
        if ($this->ressources->contains($ressource)) {
            return;
        }

        if (null !== $ressource->salle() && $ressource->salle()->lieu() !== $this) {
            throw new \DomainException('La salle de la ressource doit appartenir au même lieu.');
        }

        $this->ressources->add($ressource);
        $ressource->attachTo($this);
        if (!$this->fiche->resources()->contains($ressource)) { $this->fiche->addResource($ressource); }
        $this->touch();
    }

    public function removeRessource(RessourceLieu $ressource): void
    {
        if ($this->ressources->removeElement($ressource)) {
            $this->fiche->removeResource($ressource);
            $ressource->detachFrom($this);
            $this->touch();
        }
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

    /** @return list<string> */
    public function generaleTypologie(): array
    {
        return $this->lovValues('GENERALE_TYPOLOGIE');
    }

    /** @param list<string> $values */
    public function changeGeneraleTypologie(array $values): void
    {
        $this->replaceLovValues('GENERALE_TYPOLOGIE', $values);
    }

    /** @return list<string> */
    public function generaleChainesGroupeHot(): array
    {
        return $this->lovValues('GENERALE_CHAINES_GROUPE_HOT');
    }

    /** @param list<string> $values */
    public function changeGeneraleChainesGroupeHot(array $values): void
    {
        $this->replaceLovValues('GENERALE_CHAINES_GROUPE_HOT', $values);
    }

    public function generaleEtabRp(): bool
    {
        return $this->generaleEtabRp;
    }

    public function changeGeneraleEtabRp(bool $value): void
    {
        $this->generaleEtabRp = $value;
        $this->touch();
    }

    public function generaleWebsiteUrl(): ?string
    {
        return $this->generaleWebsiteUrl;
    }

    public function changeGeneraleWebsiteUrl(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->generaleWebsiteUrl = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function evenementsPredilection(): array
    {
        return $this->lovValues('GENERALE_EVENEMENTS_PREDILECTION');
    }

    /** @param list<string> $values */
    public function changeEvenementsPredilection(array $values): void
    {
        $this->replaceLovValues('GENERALE_EVENEMENTS_PREDILECTION', $values);
    }

    /** @deprecated Colonne de repli conservée pendant la migration. */
    public function generaleGamme(): ?string
    {
        return $this->generaleGamme;
    }

    public function changeGeneraleGamme(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->generaleGamme = $value;
        $this->touch();
    }

    public function generaleGammeLibelle(): ?string
    {
        return $this->generaleGammeLibelle;
    }

    public function changeGeneraleGammeLibelle(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->generaleGammeLibelle = $value;
        $this->touch();
    }

    public function dispoLieuPrivatisable(): bool
    {
        return $this->dispoLieuPrivatisable;
    }

    public function changeDispoLieuPrivatisable(bool $value): void
    {
        $this->dispoLieuPrivatisable = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function joursOuverture(): array
    {
        return $this->lovValues('DISPO_JOUR_OUVERTURE');
    }

    /** @param list<string> $values */
    public function changeJoursOuverture(array $values): void
    {
        $this->replaceLovValues('DISPO_JOUR_OUVERTURE', $values);
    }

    /** @deprecated Colonne de repli conservée pendant la migration. */
    public function dispoJourOuverture(): ?string
    {
        return $this->dispoJourOuverture;
    }

    public function changeDispoJourOuverture(?string $value): void
    {
        LieuLovCatalog::assertValid('DISPO_JOUR_OUVERTURE', $value);
        $value = self::normalizeNullableString($value);
        $this->dispoJourOuverture = $value;
        $this->touch();
    }

    public function dispoHeureOuvertureHeure(): ?int
    {
        return $this->dispoHeureOuvertureHeure;
    }

    public function changeDispoHeureOuvertureHeure(?int $value): void
    {
        $this->dispoHeureOuvertureHeure = $value;
        $this->touch();
    }

    public function dispoHeureOuvertureMinutes(): ?int
    {
        return $this->dispoHeureOuvertureMinutes;
    }

    public function changeDispoHeureOuvertureMinutes(?int $value): void
    {
        $this->dispoHeureOuvertureMinutes = $value;
        $this->touch();
    }

    public function dispoHeureFermetureHeure(): ?int
    {
        return $this->dispoHeureFermetureHeure;
    }

    public function changeDispoHeureFermetureHeure(?int $value): void
    {
        $this->dispoHeureFermetureHeure = $value;
        $this->touch();
    }

    public function dispoHeureFermetureMinutes(): ?int
    {
        return $this->dispoHeureFermetureMinutes;
    }

    public function changeDispoHeureFermetureMinutes(?int $value): void
    {
        $this->dispoHeureFermetureMinutes = $value;
        $this->touch();
    }

    /**
     * Horaires par jour ; repli sur l'horaire global historique décliné sur
     * les jours d'ouverture tant qu'aucune saisie par jour n'existe.
     *
     * @return array<string, array{ouverture: ?string, fermeture: ?string}>|null
     */
    public function dispoHorairesJours(): ?array
    {
        if (null !== $this->dispoHorairesJours) {
            return $this->dispoHorairesJours;
        }
        $ouverture = self::heureTexte($this->dispoHeureOuvertureHeure, $this->dispoHeureOuvertureMinutes);
        $fermeture = self::heureTexte($this->dispoHeureFermetureHeure, $this->dispoHeureFermetureMinutes);
        if (null === $ouverture && null === $fermeture) {
            return null;
        }
        $jours = [];
        foreach ($this->joursOuverture() as $jour) {
            $jours[$jour] = ['ouverture' => $ouverture, 'fermeture' => $fermeture];
        }

        return [] === $jours ? null : $jours;
    }

    /** @param array<string, array{ouverture?: ?string, fermeture?: ?string}>|null $value */
    public function changeDispoHorairesJours(?array $value): void
    {
        $nettoye = [];
        foreach ($value ?? [] as $jour => $heures) {
            $ouverture = self::normalizeNullableString($heures['ouverture'] ?? null);
            $fermeture = self::normalizeNullableString($heures['fermeture'] ?? null);
            if (null !== $ouverture || null !== $fermeture) {
                $nettoye[(string) $jour] = ['ouverture' => $ouverture, 'fermeture' => $fermeture];
            }
        }
        $this->dispoHorairesJours = [] === $nettoye ? null : $nettoye;
        if ([] !== $nettoye) {
            // Dérive l'amplitude globale (contrat marketplace inchangé). Sans
            // saisie par jour, les champs globaux historiques sont conservés.
            $ouvertures = array_filter(array_column($nettoye, 'ouverture'));
            $fermetures = array_filter(array_column($nettoye, 'fermeture'));
            [$this->dispoHeureOuvertureHeure, $this->dispoHeureOuvertureMinutes] = self::heureMinute([] === $ouvertures ? null : min($ouvertures));
            [$this->dispoHeureFermetureHeure, $this->dispoHeureFermetureMinutes] = self::heureMinute([] === $fermetures ? null : max($fermetures));
        }
        $this->touch();
    }

    private static function heureTexte(?int $heure, ?int $minutes): ?string
    {
        return null === $heure ? null : sprintf('%02d:%02d', $heure, $minutes ?? 0);
    }

    /** @return array{0: ?int, 1: ?int} */
    private static function heureMinute(?string $heure): array
    {
        if (null === $heure || 1 !== preg_match('/^(\d{1,2}):(\d{2})/', $heure, $m)) {
            return [null, null];
        }

        return [(int) $m[1], (int) $m[2]];
    }

    public function pmrAcces(): bool
    {
        return $this->pmrAcces;
    }

    public function changePmrAcces(bool $value): void
    {
        $this->pmrAcces = $value;
        $this->touch();
    }

    public function pmrDetails(): ?string
    {
        return $this->pmrDetails;
    }

    public function changePmrDetails(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->pmrDetails = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function taThematique(): array
    {
        return $this->lovValues('TA_THEMATIQUE');
    }

    /** @param list<string> $values */
    public function changeTaThematique(array $values): void
    {
        $this->replaceLovValues('TA_THEMATIQUE', $values);
    }

    /** @return list<string> */
    public function taCadreEnv(): array
    {
        return $this->lovValues('TA_CADRE_ENV');
    }

    /** @param list<string> $values */
    public function changeTaCadreEnv(array $values): void
    {
        $this->replaceLovValues('TA_CADRE_ENV', $values);
    }

    /** @return list<string> */
    public function taAmbiance(): array
    {
        return $this->lovValues('TA_AMBIANCE');
    }

    /** @param list<string> $values */
    public function changeTaAmbiance(array $values): void
    {
        $this->replaceLovValues('TA_AMBIANCE', $values);
    }

    public function descGenerale(): ?string
    {
        return $this->descGenerale;
    }

    public function changeDescGenerale(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->descGenerale = $value;
        $this->touch();
    }

    public function descGeneralePointInteret(): ?string
    {
        return $this->descGeneralePointInteret;
    }

    public function changeDescGeneralePointInteret(?string $value): void
    {
        $this->descGeneralePointInteret = self::normalizeNullableString($value);
        $this->touch();
    }

    public function atout1(): ?string
    {
        return $this->atout1;
    }

    public function changeAtout1(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->atout1 = $value;
        $this->touch();
    }

    public function atout2(): ?string
    {
        return $this->atout2;
    }

    public function changeAtout2(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->atout2 = $value;
        $this->touch();
    }

    public function atout3(): ?string
    {
        return $this->atout3;
    }

    public function changeAtout3(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->atout3 = $value;
        $this->touch();
    }

    public function atout4(): ?string
    {
        return $this->atout4;
    }

    public function changeAtout4(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->atout4 = $value;
        $this->touch();
    }

    public function atout5(): ?string
    {
        return $this->atout5;
    }

    public function changeAtout5(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->atout5 = $value;
        $this->touch();
    }

    public function chambreHebergement(): bool
    {
        return $this->chambreHebergement;
    }

    public function changeChambreHebergement(bool $value): void
    {
        $this->chambreHebergement = $value;
        $this->touch();
    }

    public function chambreNbTotal(): ?int
    {
        return $this->chambreNbTotal;
    }

    public function changeChambreNbTotal(?int $value): void
    {
        $this->chambreNbTotal = $value;
        $this->touch();
    }

    public function chambreNbTotalSingle(): ?int
    {
        return $this->chambreNbTotalSingle;
    }

    public function changeChambreNbTotalSingle(?int $value): void
    {
        $this->chambreNbTotalSingle = $value;
        $this->touch();
    }

    public function chambreNbTotalTwin(): ?int
    {
        return $this->chambreNbTotalTwin;
    }

    public function changeChambreNbTotalTwin(?int $value): void
    {
        $this->chambreNbTotalTwin = $value;
        $this->touch();
    }

    public function chambreDouble(): ?int
    {
        return $this->chambreDouble;
    }

    public function changeChambreDouble(?int $value): void
    {
        $this->chambreDouble = $value;
        $this->touch();
    }

    public function chambreCapaciteTotale(): ?int
    {
        return $this->chambreCapaciteTotale;
    }

    public function changeChambreCapaciteTotale(?int $value): void
    {
        $this->chambreCapaciteTotale = $value;
        $this->touch();
    }

    public function chambreDescGenerale(): ?string
    {
        return $this->chambreDescGenerale;
    }

    public function changeChambreDescGenerale(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->chambreDescGenerale = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function equipements(): array
    {
        return $this->lovValues('EQUIPEMENTS');
    }

    /** @param list<string> $values */
    public function changeEquipements(array $values): void
    {
        $this->replaceLovValues('EQUIPEMENTS', $values);
    }

    public function salleReunionExist(): bool
    {
        return $this->salleReunionExist;
    }

    public function changeSalleReunionExist(bool $value): void
    {
        $this->salleReunionExist = $value;
        $this->touch();
    }

    public function salleReunionNbTotal(): ?int
    {
        return $this->salleReunionNbTotal;
    }

    public function changeSalleReunionNbTotal(?int $value): void
    {
        $this->salleReunionNbTotal = $value;
        $this->touch();
    }

    public function salleReunionCapaciteMaxCocktail(): ?int
    {
        return $this->salleReunionCapaciteMaxCocktail;
    }

    public function changeSalleReunionCapaciteMaxCocktail(?int $value): void
    {
        $this->salleReunionCapaciteMaxCocktail = $value;
        $this->touch();
    }

    public function salleReunionCapaciteMaxTheatre(): ?int
    {
        return $this->salleReunionCapaciteMaxTheatre;
    }

    public function changeSalleReunionCapaciteMaxTheatre(?int $value): void
    {
        $this->salleReunionCapaciteMaxTheatre = $value;
        $this->touch();
    }

    public function salleReunionCapaciteMinTheatre(): ?int
    {
        return $this->salleReunionCapaciteMinTheatre;
    }

    public function changeSalleReunionCapaciteMinTheatre(?int $value): void
    {
        $this->salleReunionCapaciteMinTheatre = $value;
        $this->touch();
    }

    public function salleReunionSurfaceMinReunion(): ?int
    {
        return $this->salleReunionSurfaceMinReunion;
    }

    public function changeSalleReunionSurfaceMinReunion(?int $value): void
    {
        $this->salleReunionSurfaceMinReunion = $value;
        $this->touch();
    }

    public function salleReunionSurfaceMaxReunion(): ?int
    {
        return $this->salleReunionSurfaceMaxReunion;
    }

    public function changeSalleReunionSurfaceMaxReunion(?int $value): void
    {
        $this->salleReunionSurfaceMaxReunion = $value;
        $this->touch();
    }

    public function salleReunionDescSalleSeminaire(): ?string
    {
        return $this->salleReunionDescSalleSeminaire;
    }

    public function changeSalleReunionDescSalleSeminaire(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->salleReunionDescSalleSeminaire = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function services(): array
    {
        return $this->lovValues('SERVICES');
    }

    /** @param list<string> $values */
    public function changeServices(array $values): void
    {
        $this->replaceLovValues('SERVICES', $values);
    }

    /** @return list<string> */
    public function techniqueReunion(): array
    {
        return $this->lovValues('TECHNIQUE_REUNION');
    }

    /** @param list<string> $values */
    public function changeTechniqueReunion(array $values): void
    {
        $this->replaceLovValues('TECHNIQUE_REUNION', $values);
    }

    /** @return list<string> */
    public function installation(): array
    {
        return $this->lovValues('INSTALLATION');
    }

    /** @param list<string> $values */
    public function changeInstallation(array $values): void
    {
        $this->replaceLovValues('INSTALLATION', $values);
    }

    /** @return list<string> */
    public function bienEtre(): array
    {
        return $this->lovValues('BIEN_ETRE');
    }

    /** @param list<string> $values */
    public function changeBienEtre(array $values): void
    {
        $this->replaceLovValues('BIEN_ETRE', $values);
    }

    public function rseDescGenerale(): ?string
    {
        return $this->rseDescGenerale;
    }

    public function changeRseDescGenerale(?string $value): void
    {
        $this->rseDescGenerale = self::normalizeNullableString($value);
        $this->touch();
    }

    public function demarcheRse(): bool
    {
        return $this->demarcheRse;
    }

    public function changeDemarcheRse(bool $value): void
    {
        $this->demarcheRse = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function achatResponsable(): array
    {
        return $this->lovValues('ACHAT_RESPONSABLE');
    }

    /** @param list<string> $values */
    public function changeAchatResponsable(array $values): void
    {
        $this->replaceLovValues('ACHAT_RESPONSABLE', $values);
    }

    /** @return list<string> */
    public function impactEnv(): array
    {
        return $this->lovValues('IMPACT_ENV');
    }

    /** @param list<string> $values */
    public function changeImpactEnv(array $values): void
    {
        $this->replaceLovValues('IMPACT_ENV', $values);
    }

    /** @return list<string> */
    public function impactSocial(): array
    {
        return $this->lovValues('IMPACT_SOCIAL');
    }

    /** @param list<string> $values */
    public function changeImpactSocial(array $values): void
    {
        $this->replaceLovValues('IMPACT_SOCIAL', $values);
    }

    /** @return list<string> */
    public function volumeAchatCatEsatStpa(): array
    {
        return $this->lovValues('VOLUME_ACHAT_CAT_ESAT_STPA');
    }

    /** @param list<string> $values */
    public function changeVolumeAchatCatEsatStpa(array $values): void
    {
        $this->replaceLovValues('VOLUME_ACHAT_CAT_ESAT_STPA', $values);
    }

    /** @return list<string> */
    public function mobilite(): array
    {
        return $this->lovValues('MOBILITE');
    }

    /** @param list<string> $values */
    public function changeMobilite(array $values): void
    {
        $this->replaceLovValues('MOBILITE', $values);
    }

    /** @return list<string> */
    public function certification(): array
    {
        return $this->lovValues('CERTIFICATION');
    }

    /** @param list<string> $values */
    public function changeCertification(array $values): void
    {
        $this->replaceLovValues('CERTIFICATION', $values);
    }

    /** @return list<string> */
    public function loisirInterne(): array
    {
        return $this->loisirInterne;
    }

    /** @param list<string> $values */
    public function changeLoisirInterne(array $values): void
    {
        $values = array_map(static fn (string $value): string => trim($value), $values);
        $this->loisirInterne = array_values(array_unique(array_filter($values, static fn (string $value): bool => '' !== $value)));
        $this->touch();
    }

    /** @return list<string> */
    public function loisirExterneNomPresta(): array
    {
        return $this->loisirExterneNomPresta;
    }

    /** @param list<string> $values */
    public function changeLoisirExterneNomPresta(array $values): void
    {
        $values = array_map(static fn (string $value): string => trim($value), $values);
        $this->loisirExterneNomPresta = array_values(array_unique(array_filter($values, static fn (string $value): bool => '' !== $value)));
        $this->touch();
    }

    /** @return list<string> */
    public function loisirExterneNomActivite(): array
    {
        return $this->loisirExterneNomActivite;
    }

    /** @param list<string> $values */
    public function changeLoisirExterneNomActivite(array $values): void
    {
        $values = array_map(static fn (string $value): string => trim($value), $values);
        $this->loisirExterneNomActivite = array_values(array_unique(array_filter($values, static fn (string $value): bool => '' !== $value)));
        $this->touch();
    }

    public function restaurantTotal(): ?int
    {
        return $this->restaurantTotal;
    }

    public function changeRestaurantTotal(?int $value): void
    {
        $this->restaurantTotal = $value;
        $this->touch();
    }

    public function restaurantSalleRestauration(): ?int
    {
        return $this->restaurantSalleRestauration;
    }

    public function changeRestaurantSalleRestauration(?int $value): void
    {
        $this->restaurantSalleRestauration = $value;
        $this->touch();
    }

    public function restaurantCapaciteDebout(): ?int
    {
        return $this->restaurantCapaciteDebout;
    }

    public function changeRestaurantCapaciteDebout(?int $value): void
    {
        $this->restaurantCapaciteDebout = $value;
        $this->touch();
    }

    public function restaurantCapaciteAssis(): ?int
    {
        return $this->restaurantCapaciteAssis;
    }

    public function changeRestaurantCapaciteAssis(?int $value): void
    {
        $this->restaurantCapaciteAssis = $value;
        $this->touch();
    }

    public function restaurantSoireeDansante(): ?bool
    {
        return $this->restaurantSoireeDansante;
    }

    public function changeRestaurantSoireeDansante(?bool $value): void
    {
        $this->restaurantSoireeDansante = $value;
        $this->touch();
    }

    public function restaurantCocktailDinatoire(): ?bool
    {
        return $this->restaurantCocktailDinatoire;
    }

    public function changeRestaurantCocktailDinatoire(?bool $value): void
    {
        $this->restaurantCocktailDinatoire = $value;
        $this->touch();
    }

    public function restaurantTraiteurSurPlace(): ?bool
    {
        return $this->restaurantTraiteurSurPlace;
    }

    public function changeRestaurantTraiteurSurPlace(?bool $value): void
    {
        $this->restaurantTraiteurSurPlace = $value;
        $this->touch();
    }

    public function restaurantInterventionTraiteurExterne(): ?bool
    {
        return $this->restaurantInterventionTraiteurExterne;
    }

    public function changeRestaurantInterventionTraiteurExterne(?bool $value): void
    {
        $this->restaurantInterventionTraiteurExterne = $value;
        $this->touch();
    }

    public function restaurantTrateurExterneClient(): ?bool
    {
        return $this->restaurantTrateurExterneClient;
    }

    public function changeRestaurantTrateurExterneClient(?bool $value): void
    {
        $this->restaurantTrateurExterneClient = $value;
        $this->touch();
    }

    public function restaurantPrivatisable(): ?bool
    {
        return $this->restaurantPrivatisable;
    }

    public function changeRestaurantPrivatisable(?bool $value): void
    {
        $this->restaurantPrivatisable = $value;
        $this->touch();
    }

    public function restaurantHeureInterruptionMusique(): ?string
    {
        return $this->restaurantHeureInterruptionMusique;
    }

    public function changeRestaurantHeureInterruptionMusique(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->restaurantHeureInterruptionMusique = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function typeCuisine(): array
    {
        return $this->lovValues('TYPE_CUISINE');
    }

    /** @param list<string> $values */
    public function changeTypeCuisine(array $values): void
    {
        $this->replaceLovValues('TYPE_CUISINE', $values);
    }

    /** @return list<string> */
    public function serviceRestauration(): array
    {
        return $this->lovValues('SERVICE_RESTAURATION');
    }

    /** @param list<string> $values */
    public function changeServiceRestauration(array $values): void
    {
        $this->replaceLovValues('SERVICE_RESTAURATION', $values);
    }

    public function generaleYoutube(): ?string
    {
        return $this->generaleYoutube;
    }

    public function changeGeneraleYoutube(?string $value): void
    {
        $value = self::normalizeNullableString($value);
        $this->generaleYoutube = $value;
        $this->touch();
    }

    /** @return list<string> */
    public function modePaiementCarteListe(): array
    {
        return $this->lovValues('MODE_PAIEMENT_CARTE_LISTE');
    }

    /** @param list<string> $values */
    public function changeModePaiementCarteListe(array $values): void
    {
        $this->replaceLovValues('MODE_PAIEMENT_CARTE_LISTE', $values);
    }

    /** @return list<string> */
    public function sitePremium(): array
    {
        return $this->lovValues('SITE_PREMIUM');
    }

    /** @param list<string> $values */
    public function changeSitePremium(array $values): void
    {
        $this->replaceLovValues('SITE_PREMIUM', $values);
    }

    public function miceStatut(): ?string
    {
        return $this->miceStatut;
    }

    public function changeMiceStatut(?string $value): void
    {
        LieuLovCatalog::assertValid('MICE_STATUT', $value);
        $value = self::normalizeNullableString($value);
        $this->miceStatut = $value;
        $this->touch();
    }

    public function afficherContact(): bool
    {
        return $this->afficherContact;
    }

    public function changeAfficherContact(bool $value): void
    {
        $this->afficherContact = $value;
        $this->touch();
    }


    public function markChanged(): void
    {
        $this->touch();
    }

    protected function touch(): void
    {
        $this->touchDetail();
        $this->fiche->markChanged();
    }

    /** @return list<string> */
    private function lovValues(string $attributeCode): array
    {
        return array_map(
            static fn (int $valueId): string => LieuLovCatalog::valueCode($valueId),
            $this->fiche->valueIdsFor($attributeCode),
        );
    }

    /** @param list<string> $values */
    private function replaceLovValues(string $attributeCode, array $values): void
    {
        $values = array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim($value), $values),
            static fn (string $value): bool => '' !== $value,
        )));
        LieuLovCatalog::assertValidMany($attributeCode, $values);
        $this->fiche->replaceAttributeValues(
            $attributeCode,
            array_map(static fn (string $value): int => LieuLovCatalog::valueId($attributeCode, $value), $values),
        );
        $this->touch();
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

}
