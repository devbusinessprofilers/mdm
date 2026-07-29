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
    #[ORM\Column(name: 'heberg_group_tarif_chambre_single', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $hebergGroupTarifChambreSingle = null;
    #[ORM\Column(name: 'heberg_group_tarif_chambre_twin', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $hebergGroupTarifChambreTwin = null;
    #[ORM\Column(name: 'heberg_group_tarif_chambre_double', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $hebergGroupTarifChambreDouble = null;

    public function __construct(Lieu $lieu)
    {
        $this->lieu = $lieu;
    }

    public function seminaireJourneeDemiJourneeEtude(): ?string { return $this->seminaireJourneeDemiJourneeEtude; }
    public function changeSeminaireJourneeDemiJourneeEtude(?string $value): void { $this->seminaireJourneeDemiJourneeEtude = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function seminaireJourneeJourneeEtude(): ?string { return $this->seminaireJourneeJourneeEtude; }
    public function changeSeminaireJourneeJourneeEtude(?string $value): void { $this->seminaireJourneeJourneeEtude = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function seminaireJourneeDemiJourneeEtudeCocktail(): ?string { return $this->seminaireJourneeDemiJourneeEtudeCocktail; }
    public function changeSeminaireJourneeDemiJourneeEtudeCocktail(?string $value): void { $this->seminaireJourneeDemiJourneeEtudeCocktail = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function seminaireJourneeJourneeEtudeCocktail(): ?string { return $this->seminaireJourneeJourneeEtudeCocktail; }
    public function changeSeminaireJourneeJourneeEtudeCocktail(?string $value): void { $this->seminaireJourneeJourneeEtudeCocktail = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function seminaireNuiteeSemiResidentiel(): ?string { return $this->seminaireNuiteeSemiResidentiel; }
    public function changeSeminaireNuiteeSemiResidentiel(?string $value): void { $this->seminaireNuiteeSemiResidentiel = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function seminaireNuiteeResidentiel(): ?string { return $this->seminaireNuiteeResidentiel; }
    public function changeSeminaireNuiteeResidentiel(?string $value): void { $this->seminaireNuiteeResidentiel = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function seminaireNuiteeResidentielAllInclusive(): ?string { return $this->seminaireNuiteeResidentielAllInclusive; }
    public function changeSeminaireNuiteeResidentielAllInclusive(?string $value): void { $this->seminaireNuiteeResidentielAllInclusive = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function locSalleSeulDemiJournee(): ?string { return $this->locSalleSeulDemiJournee; }
    public function changeLocSalleSeulDemiJournee(?string $value): void { $this->locSalleSeulDemiJournee = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function locSalleSeulJournee(): ?string { return $this->locSalleSeulJournee; }
    public function changeLocSalleSeulJournee(?string $value): void { $this->locSalleSeulJournee = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function locSalleSeulSoiree(): ?string { return $this->locSalleSeulSoiree; }
    public function changeLocSalleSeulSoiree(?string $value): void { $this->locSalleSeulSoiree = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function csCocktailDejeunatoire10Pers(): ?string { return $this->csCocktailDejeunatoire10Pers; }
    public function changeCsCocktailDejeunatoire10Pers(?string $value): void { $this->csCocktailDejeunatoire10Pers = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function csCocktailDinatoire(): ?string { return $this->csCocktailDinatoire; }
    public function changeCsCocktailDinatoire(?string $value): void { $this->csCocktailDinatoire = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function csSoireeDansante(): ?string { return $this->csSoireeDansante; }
    public function changeCsSoireeDansante(?string $value): void { $this->csSoireeDansante = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function csSoireeDinerAssis(): ?string { return $this->csSoireeDinerAssis; }
    public function changeCsSoireeDinerAssis(?string $value): void { $this->csSoireeDinerAssis = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function tarifRestDejeunerAssis(): ?string { return $this->tarifRestDejeunerAssis; }
    public function changeTarifRestDejeunerAssis(?string $value): void { $this->tarifRestDejeunerAssis = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function tarifRestDinerAssis(): ?string { return $this->tarifRestDinerAssis; }
    public function changeTarifRestDinerAssis(?string $value): void { $this->tarifRestDinerAssis = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function tarifRestOptVin(): ?string { return $this->tarifRestOptVin; }
    public function changeTarifRestOptVin(?string $value): void { $this->tarifRestOptVin = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function tarifRestOptAlcool(): ?string { return $this->tarifRestOptAlcool; }
    public function changeTarifRestOptAlcool(?string $value): void { $this->tarifRestOptAlcool = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function tarifRestForfaitPersonalise(): ?string { return $this->tarifRestForfaitPersonalise; }
    public function changeTarifRestForfaitPersonalise(?string $value): void { $this->tarifRestForfaitPersonalise = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function hebergGroupTarifChambreSingle(): ?string { return $this->hebergGroupTarifChambreSingle; }
    public function changeHebergGroupTarifChambreSingle(?string $value): void { $this->hebergGroupTarifChambreSingle = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function hebergGroupTarifChambreTwin(): ?string { return $this->hebergGroupTarifChambreTwin; }
    public function changeHebergGroupTarifChambreTwin(?string $value): void { $this->hebergGroupTarifChambreTwin = self::normalizeDecimal($value); $this->lieu->markChanged(); }
    public function hebergGroupTarifChambreDouble(): ?string { return $this->hebergGroupTarifChambreDouble; }
    public function changeHebergGroupTarifChambreDouble(?string $value): void { $this->hebergGroupTarifChambreDouble = self::normalizeDecimal($value); $this->lieu->markChanged(); }

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
}
