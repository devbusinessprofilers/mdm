<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pim_lieu_tarification')]
class LieuTarification
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'tarification')]
    #[ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Lieu $lieu;

    #[ORM\Column(name: 'seminaire_journee_demi_journee_etude', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireJourneeDemiJourneeEtude = null;
    #[ORM\Column(name: 'seminaire_journee_journee_etude', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireJourneeJourneeEtude = null;
    #[ORM\Column(name: 'seminaire_journee_demi_journee_etude_cocktail', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireJourneeDemiJourneeEtudeCocktail = null;
    #[ORM\Column(name: 'seminaire_journee_journee_etude_cocktail', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireJourneeJourneeEtudeCocktail = null;
    #[ORM\Column(name: 'seminaire_nuitee_semi_residentiel', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireNuiteeSemiResidentiel = null;
    #[ORM\Column(name: 'seminaire_nuitee_residentiel', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireNuiteeResidentiel = null;
    #[ORM\Column(name: 'seminaire_nuitee_residentiel_all_inclusive', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $seminaireNuiteeResidentielAllInclusive = null;
    #[ORM\Column(name: 'loc_salle_seul_demi_journee', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $locSalleSeulDemiJournee = null;
    #[ORM\Column(name: 'loc_salle_seul_journee', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $locSalleSeulJournee = null;
    #[ORM\Column(name: 'loc_salle_seul_soiree', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $locSalleSeulSoiree = null;
    #[ORM\Column(name: 'cs_cocktail_dejeunatoire_10_pers', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $csCocktailDejeunatoire10Pers = null;
    #[ORM\Column(name: 'cs_cocktail_dinatoire', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $csCocktailDinatoire = null;
    #[ORM\Column(name: 'cs_soiree_dansante', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $csSoireeDansante = null;
    #[ORM\Column(name: 'cs_soiree_diner_assis', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $csSoireeDinerAssis = null;
    #[ORM\Column(name: 'tarif_rest_dejeuner_assis', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $tarifRestDejeunerAssis = null;
    #[ORM\Column(name: 'tarif_rest_diner_assis', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $tarifRestDinerAssis = null;
    #[ORM\Column(name: 'tarif_rest_opt_vin', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $tarifRestOptVin = null;
    #[ORM\Column(name: 'tarif_rest_opt_alcool', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $tarifRestOptAlcool = null;
    #[ORM\Column(name: 'tarif_rest_forfait_personalise', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $tarifRestForfaitPersonalise = null;
    // Offre spéciale du bloc « Tarifs & formules » (bloc promo marketplace).
    #[ORM\Column(name: 'offre_speciale', type: Types::TEXT, nullable: true)] private ?string $offreSpeciale = null;
    #[ORM\Column(name: 'promotion_debut', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $promotionDebut = null;
    #[ORM\Column(name: 'promotion_fin', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $promotionFin = null;
    #[ORM\Column(name: 'heberg_group_tarif_chambre_single', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $hebergGroupTarifChambreSingle = null;
    #[ORM\Column(name: 'heberg_group_tarif_chambre_twin', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $hebergGroupTarifChambreTwin = null;
    #[ORM\Column(name: 'heberg_group_tarif_chambre_double', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $hebergGroupTarifChambreDouble = null;
    public function __construct(Lieu $lieu)
    {
        $this->lieu = $lieu;
    }

    public function seminaireJourneeDemiJourneeEtude(): ?string
    {
        return $this->seminaireJourneeDemiJourneeEtude;
    }

    public function changeSeminaireJourneeDemiJourneeEtude(?string $value): void
    {
        $this->assign('seminaireJourneeDemiJourneeEtude', self::normalizeDecimal($value));
    }

    public function seminaireJourneeJourneeEtude(): ?string
    {
        return $this->seminaireJourneeJourneeEtude;
    }

    public function changeSeminaireJourneeJourneeEtude(?string $value): void
    {
        $this->assign('seminaireJourneeJourneeEtude', self::normalizeDecimal($value));
    }

    public function seminaireJourneeDemiJourneeEtudeCocktail(): ?string
    {
        return $this->seminaireJourneeDemiJourneeEtudeCocktail;
    }

    public function changeSeminaireJourneeDemiJourneeEtudeCocktail(?string $value): void
    {
        $this->assign('seminaireJourneeDemiJourneeEtudeCocktail', self::normalizeDecimal($value));
    }

    public function seminaireJourneeJourneeEtudeCocktail(): ?string
    {
        return $this->seminaireJourneeJourneeEtudeCocktail;
    }

    public function changeSeminaireJourneeJourneeEtudeCocktail(?string $value): void
    {
        $this->assign('seminaireJourneeJourneeEtudeCocktail', self::normalizeDecimal($value));
    }

    public function seminaireNuiteeSemiResidentiel(): ?string
    {
        return $this->seminaireNuiteeSemiResidentiel;
    }

    public function changeSeminaireNuiteeSemiResidentiel(?string $value): void
    {
        $this->assign('seminaireNuiteeSemiResidentiel', self::normalizeDecimal($value));
    }

    public function seminaireNuiteeResidentiel(): ?string
    {
        return $this->seminaireNuiteeResidentiel;
    }

    public function changeSeminaireNuiteeResidentiel(?string $value): void
    {
        $this->assign('seminaireNuiteeResidentiel', self::normalizeDecimal($value));
    }

    public function seminaireNuiteeResidentielAllInclusive(): ?string
    {
        return $this->seminaireNuiteeResidentielAllInclusive;
    }

    public function changeSeminaireNuiteeResidentielAllInclusive(?string $value): void
    {
        $this->assign('seminaireNuiteeResidentielAllInclusive', self::normalizeDecimal($value));
    }

    public function locSalleSeulDemiJournee(): ?string
    {
        return $this->locSalleSeulDemiJournee;
    }

    public function changeLocSalleSeulDemiJournee(?string $value): void
    {
        $this->assign('locSalleSeulDemiJournee', self::normalizeDecimal($value));
    }

    public function locSalleSeulJournee(): ?string
    {
        return $this->locSalleSeulJournee;
    }

    public function changeLocSalleSeulJournee(?string $value): void
    {
        $this->assign('locSalleSeulJournee', self::normalizeDecimal($value));
    }

    public function locSalleSeulSoiree(): ?string
    {
        return $this->locSalleSeulSoiree;
    }

    public function changeLocSalleSeulSoiree(?string $value): void
    {
        $this->assign('locSalleSeulSoiree', self::normalizeDecimal($value));
    }

    public function csCocktailDejeunatoire10Pers(): ?string
    {
        return $this->csCocktailDejeunatoire10Pers;
    }

    public function changeCsCocktailDejeunatoire10Pers(?string $value): void
    {
        $this->assign('csCocktailDejeunatoire10Pers', self::normalizeDecimal($value));
    }

    public function csCocktailDinatoire(): ?string
    {
        return $this->csCocktailDinatoire;
    }

    public function changeCsCocktailDinatoire(?string $value): void
    {
        $this->assign('csCocktailDinatoire', self::normalizeDecimal($value));
    }

    public function csSoireeDansante(): ?string
    {
        return $this->csSoireeDansante;
    }

    public function changeCsSoireeDansante(?string $value): void
    {
        $this->assign('csSoireeDansante', self::normalizeDecimal($value));
    }

    public function csSoireeDinerAssis(): ?string
    {
        return $this->csSoireeDinerAssis;
    }

    public function changeCsSoireeDinerAssis(?string $value): void
    {
        $this->assign('csSoireeDinerAssis', self::normalizeDecimal($value));
    }

    public function tarifRestDejeunerAssis(): ?string
    {
        return $this->tarifRestDejeunerAssis;
    }

    public function changeTarifRestDejeunerAssis(?string $value): void
    {
        $this->assign('tarifRestDejeunerAssis', self::normalizeDecimal($value));
    }

    public function tarifRestDinerAssis(): ?string
    {
        return $this->tarifRestDinerAssis;
    }

    public function changeTarifRestDinerAssis(?string $value): void
    {
        $this->assign('tarifRestDinerAssis', self::normalizeDecimal($value));
    }

    public function tarifRestOptVin(): ?string
    {
        return $this->tarifRestOptVin;
    }

    public function changeTarifRestOptVin(?string $value): void
    {
        $this->assign('tarifRestOptVin', self::normalizeDecimal($value));
    }

    public function tarifRestOptAlcool(): ?string
    {
        return $this->tarifRestOptAlcool;
    }

    public function changeTarifRestOptAlcool(?string $value): void
    {
        $this->assign('tarifRestOptAlcool', self::normalizeDecimal($value));
    }

    public function tarifRestForfaitPersonalise(): ?string
    {
        return $this->tarifRestForfaitPersonalise;
    }

    public function changeTarifRestForfaitPersonalise(?string $value): void
    {
        $this->assign('tarifRestForfaitPersonalise', self::normalizeDecimal($value));
    }

    public function hebergGroupTarifChambreSingle(): ?string
    {
        return $this->hebergGroupTarifChambreSingle;
    }

    public function changeHebergGroupTarifChambreSingle(?string $value): void
    {
        $this->assign('hebergGroupTarifChambreSingle', self::normalizeDecimal($value));
    }

    public function hebergGroupTarifChambreTwin(): ?string
    {
        return $this->hebergGroupTarifChambreTwin;
    }

    public function changeHebergGroupTarifChambreTwin(?string $value): void
    {
        $this->assign('hebergGroupTarifChambreTwin', self::normalizeDecimal($value));
    }

    public function hebergGroupTarifChambreDouble(): ?string
    {
        return $this->hebergGroupTarifChambreDouble;
    }

    public function changeHebergGroupTarifChambreDouble(?string $value): void
    {
        $this->assign('hebergGroupTarifChambreDouble', self::normalizeDecimal($value));
    }

    public function offreSpeciale(): ?string
    {
        return $this->offreSpeciale;
    }

    public function changeOffreSpeciale(?string $value): void
    {
        $value = null === $value ? null : trim($value);
        $this->assign('offreSpeciale', '' === $value ? null : $value);
    }

    public function promotionDebut(): ?\DateTimeImmutable
    {
        return $this->promotionDebut;
    }

    public function changePromotionDebut(?\DateTimeImmutable $value): void
    {
        $this->assign('promotionDebut', $value);
    }

    public function promotionFin(): ?\DateTimeImmutable
    {
        return $this->promotionFin;
    }

    public function changePromotionFin(?\DateTimeImmutable $value): void
    {
        $this->assign('promotionFin', $value);
    }

    private static function normalizeDecimal(?string $value): ?string
    {
        if (null !== $value) {
            $value = trim($value);
            $value = '' === $value ? null : $value;
        }
        if (null !== $value && !is_numeric($value)) {
            throw new \InvalidArgumentException('A decimal value must be numeric.');
        }

        return $value;
    }

    /**
     * Affecte la propriété et marque la fiche modifiée, seulement si la valeur
     * change : enregistrer sans rien modifier ne remet pas la fiche « en cours »
     * (même règle que les LOV et les sites de diffusion).
     */
    private function assign(string $property, mixed $value): void
    {
        $propriete = new \ReflectionProperty($this, $property);
        if ($propriete->isInitialized($this)) {
            $courante = $this->{$property};
            if ($courante === $value || (is_object($value) && is_object($courante) && $courante::class === $value::class && $courante == $value)) {
                return;
            }
        }
        $this->{$property} = $value;
        $this->lieu->markChanged();
    }
}
