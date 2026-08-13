<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Repository\LieuRepository;

final class LieuImportSchema extends AbstractFicheImportSchema
{
    public const MAX_SALLES = 20;
    public const MAX_PERIODES_FERMETURE = 10;
    public const MAX_ACCES = 10;

    private const LOV_ATTRIBUTES = [
        'GENERALE_TYPOLOGIE', 'GENERALE_CHAINES_GROUPE_HOT', 'GENERALE_EVENEMENTS_PREDILECTION',
        'DISPO_JOUR_OUVERTURE', 'TA_THEMATIQUE', 'TA_CADRE_ENV', 'TA_AMBIANCE', 'EQUIPEMENTS',
        'SERVICES', 'TECHNIQUE_REUNION', 'INSTALLATION', 'BIEN_ETRE', 'ACHAT_RESPONSABLE',
        'IMPACT_ENV', 'IMPACT_SOCIAL', 'VOLUME_ACHAT_CAT_ESAT_STPA', 'MOBILITE', 'CERTIFICATION',
        'TYPE_CUISINE', 'SERVICE_RESTAURATION', 'MODE_PAIEMENT_CARTE_LISTE', 'SITE_PREMIUM',
        'MICE_STATUT', 'INFO_LEGALE_TVA', 'COND_PAIE_ACC_SIGNATURE', 'COND_PAIE_ANN_SIGNATURE',
        'DATE_PAIEMENT_SOLD',
    ];

    public function __construct(private readonly LieuRepository $lieux)
    {
    }

    public function type(): TypeFiche
    {
        return TypeFiche::Lieu;
    }

    public function ficheColumns(): array
    {
        return [
            ...$this->commonColumns(),
            $this->lovMulti('generaleTypologie', 'GENERALE_TYPOLOGIE'),
            $this->lovMulti('generaleChainesGroupeHot', 'GENERALE_CHAINES_GROUPE_HOT'),
            $this->bool('generaleEtabRp'),
            $this->text('generaleWebsiteUrl', Lieu::WEBSITE_MAX_LENGTH),
            $this->lovMulti('evenementsPredilection', 'GENERALE_EVENEMENTS_PREDILECTION'),
            $this->text('generaleGammeLibelle', 255),
            $this->bool('dispoLieuPrivatisable'),
            $this->lovMulti('joursOuverture', 'DISPO_JOUR_OUVERTURE'),
            $this->int('dispoHeureOuvertureHeure'),
            $this->int('dispoHeureOuvertureMinutes'),
            $this->int('dispoHeureFermetureHeure'),
            $this->int('dispoHeureFermetureMinutes'),
            $this->bool('pmrAcces'),
            $this->text('pmrDetails', Lieu::PMR_DETAILS_MAX_LENGTH),
            $this->lovMulti('taThematique', 'TA_THEMATIQUE'),
            $this->lovMulti('taCadreEnv', 'TA_CADRE_ENV'),
            $this->lovMulti('taAmbiance', 'TA_AMBIANCE'),
            $this->text('descGenerale', Lieu::DESCRIPTION_MAX_LENGTH),
            $this->text('descGeneralePointInteret'),
            $this->text('atout1', Lieu::ATOUT_MAX_LENGTH),
            $this->text('atout2', Lieu::ATOUT_MAX_LENGTH),
            $this->text('atout3', Lieu::ATOUT_MAX_LENGTH),
            $this->text('atout4', Lieu::ATOUT_MAX_LENGTH),
            $this->text('atout5', Lieu::ATOUT_MAX_LENGTH),
            $this->bool('chambreHebergement'),
            $this->int('chambreNbTotal'),
            $this->int('chambreNbTotalSingle'),
            $this->int('chambreNbTotalTwin'),
            $this->int('chambreDouble'),
            $this->int('chambreCapaciteTotale'),
            $this->text('chambreDescGenerale', Lieu::DESCRIPTION_MAX_LENGTH),
            $this->lovMulti('equipements', 'EQUIPEMENTS'),
            $this->bool('salleReunionExist'),
            $this->int('salleReunionNbTotal'),
            $this->int('salleReunionCapaciteMaxCocktail'),
            $this->int('salleReunionCapaciteMaxTheatre'),
            $this->int('salleReunionCapaciteMinTheatre'),
            $this->int('salleReunionSurfaceMinReunion'),
            $this->int('salleReunionSurfaceMaxReunion'),
            $this->text('salleReunionDescSalleSeminaire'),
            $this->lovMulti('services', 'SERVICES'),
            $this->lovMulti('techniqueReunion', 'TECHNIQUE_REUNION'),
            $this->lovMulti('installation', 'INSTALLATION'),
            $this->lovMulti('bienEtre', 'BIEN_ETRE'),
            $this->text('rseDescGenerale'),
            $this->lovMulti('achatResponsable', 'ACHAT_RESPONSABLE'),
            $this->lovMulti('impactEnv', 'IMPACT_ENV'),
            $this->lovMulti('impactSocial', 'IMPACT_SOCIAL'),
            $this->lovMulti('volumeAchatCatEsatStpa', 'VOLUME_ACHAT_CAT_ESAT_STPA'),
            $this->lovMulti('mobilite', 'MOBILITE'),
            $this->lovMulti('certification', 'CERTIFICATION'),
            $this->list('loisirInterne'),
            $this->list('loisirExterneNomPresta'),
            $this->list('loisirExterneNomActivite'),
            $this->int('restaurantTotal'),
            $this->int('restaurantSalleRestauration'),
            $this->int('restaurantCapaciteDebout'),
            $this->int('restaurantCapaciteAssis'),
            $this->boolNull('restaurantSoireeDansante'),
            $this->boolNull('restaurantCocktailDinatoire'),
            $this->boolNull('restaurantTraiteurSurPlace'),
            $this->boolNull('restaurantInterventionTraiteurExterne'),
            $this->boolNull('restaurantTrateurExterneClient'),
            $this->boolNull('restaurantPrivatisable'),
            $this->text('restaurantHeureInterruptionMusique', 255),
            $this->lovMulti('typeCuisine', 'TYPE_CUISINE'),
            $this->lovMulti('serviceRestauration', 'SERVICE_RESTAURATION'),
            $this->text('generaleYoutube', 255),
            $this->lovMulti('modePaiementCarteListe', 'MODE_PAIEMENT_CARTE_LISTE'),
            $this->lovMulti('sitePremium', 'SITE_PREMIUM'),
            $this->lovMono('miceStatut', 'MICE_STATUT'),
            $this->bool('afficherContact'),
            // Bloc administratif
            $this->text('infoLegaleNom', targetPath: 'administratif'),
            $this->text('infoLegaleFormeJuridique', targetPath: 'administratif'),
            $this->text('infoLegaleRuePostal', targetPath: 'administratif'),
            $this->text('infoLegaleAdresse2', targetPath: 'administratif'),
            $this->text('infoLegaleCodePostal', targetPath: 'administratif'),
            $this->text('infoLegaleVille', targetPath: 'administratif'),
            $this->text('inforLegalePays', targetPath: 'administratif'),
            $this->text('infoLegaleSiret', targetPath: 'administratif'),
            $this->text('infoLegaleNumTva', targetPath: 'administratif'),
            $this->boolNull('infoLegaleAssujettiTva', targetPath: 'administratif'),
            $this->lovMono('infoLegaleTva', 'INFO_LEGALE_TVA', targetPath: 'administratif'),
            $this->text('infoLegaleTypeDeProcedureJudiciaire', targetPath: 'administratif'),
            $this->text('adresseFacturationNom', targetPath: 'administratif'),
            $this->text('adresseFacturationRuePostal', targetPath: 'administratif'),
            $this->text('adresseFacturationCodePostal', targetPath: 'administratif'),
            $this->text('adresseFacturationVille', targetPath: 'administratif'),
            $this->text('adresseFacturationPays', targetPath: 'administratif'),
            $this->text('adresseFacturationNumTva', targetPath: 'administratif'),
            $this->text('contactFacturationNom', targetPath: 'administratif'),
            $this->text('contactFacturationPrenom', targetPath: 'administratif'),
            $this->text('contactFacturationEmail', targetPath: 'administratif'),
            $this->text('contactFacturationTelephone', targetPath: 'administratif'),
            $this->text('modePaiementBic', targetPath: 'administratif'),
            $this->text('modePaiementIban', targetPath: 'administratif'),
            $this->boolNull('modePaiementAcceptDeductionCom', targetPath: 'administratif'),
            $this->boolNull('modePaiementAffacturage', targetPath: 'administratif'),
            $this->text('affacturageBic', targetPath: 'administratif'),
            $this->text('affacturageIban', targetPath: 'administratif'),
            $this->lovMono('condPaieAccSignature', 'COND_PAIE_ACC_SIGNATURE', targetPath: 'administratif'),
            $this->lovMono('condPaieAnnSignature', 'COND_PAIE_ANN_SIGNATURE', targetPath: 'administratif'),
            $this->text('commissionApplicable', targetPath: 'administratif'),
            $this->lovMono('datePaiementSold', 'DATE_PAIEMENT_SOLD', targetPath: 'administratif'),
            $this->text('convPartSigneeLe', targetPath: 'administratif'),
            $this->text('convPartTaux', targetPath: 'administratif'),
            $this->text('signataireEmail', targetPath: 'administratif'),
            $this->text('signatairePrenom', targetPath: 'administratif'),
            $this->text('signataireNom', targetPath: 'administratif'),
            // Bloc tarification
            $this->text('offreSpeciale', targetPath: 'tarification'),
            new ColumnDefinition('tarification_promotion_debut', ColumnKind::Date, 'promotionDebut', targetPath: 'tarification', help: 'date AAAA-MM-JJ'),
            new ColumnDefinition('tarification_promotion_fin', ColumnKind::Date, 'promotionFin', targetPath: 'tarification', help: 'date AAAA-MM-JJ'),
            $this->decimal('seminaireJourneeDemiJourneeEtude', 'tarification'),
            $this->decimal('seminaireJourneeJourneeEtude', 'tarification'),
            $this->decimal('seminaireJourneeDemiJourneeEtudeCocktail', 'tarification'),
            $this->decimal('seminaireJourneeJourneeEtudeCocktail', 'tarification'),
            $this->decimal('seminaireNuiteeSemiResidentiel', 'tarification'),
            $this->decimal('seminaireNuiteeResidentiel', 'tarification'),
            $this->decimal('seminaireNuiteeResidentielAllInclusive', 'tarification'),
            $this->decimal('locSalleSeulDemiJournee', 'tarification'),
            $this->decimal('locSalleSeulJournee', 'tarification'),
            $this->decimal('locSalleSeulSoiree', 'tarification'),
            $this->decimal('csCocktailDejeunatoire10Pers', 'tarification'),
            $this->decimal('csCocktailDinatoire', 'tarification'),
            $this->decimal('csSoireeDansante', 'tarification'),
            $this->decimal('csSoireeDinerAssis', 'tarification'),
            $this->decimal('tarifRestDejeunerAssis', 'tarification'),
            $this->decimal('tarifRestDinerAssis', 'tarification'),
            $this->decimal('tarifRestOptVin', 'tarification'),
            $this->decimal('tarifRestOptAlcool', 'tarification'),
            $this->decimal('tarifRestForfaitPersonalise', 'tarification'),
            $this->decimal('hebergGroupTarifChambreSingle', 'tarification'),
            $this->decimal('hebergGroupTarifChambreTwin', 'tarification'),
            $this->decimal('hebergGroupTarifChambreDouble', 'tarification'),
        ];
    }

    public function collections(): array
    {
        return [
            new CollectionSchema('salle', self::MAX_SALLES, Salle::class, 'addSalle', 'salles', $this->salleColumns()),
            new CollectionSchema('periode_fermeture', self::MAX_PERIODES_FERMETURE, PeriodeFermeture::class, 'addPeriodeFermeture', 'periodesFermeture', [
                new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
                new ColumnDefinition('date_debut', ColumnKind::Date, 'dateDebut'),
                new ColumnDefinition('date_fin', ColumnKind::Date, 'dateFin'),
            ]),
            new CollectionSchema('acces', self::MAX_ACCES, AccesLieu::class, 'addAcces', 'acces', [
                new ColumnDefinition('type', ColumnKind::Enum, 'type', enumClass: TypeAccesLieu::class, required: true, nullable: false),
                new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
                new ColumnDefinition('distance_kilometres', ColumnKind::Decimal, 'distanceKilometres'),
                new ColumnDefinition('duree_minutes', ColumnKind::Int, 'dureeMinutes'),
                new ColumnDefinition('mode_transport', ColumnKind::Text, 'modeTransport'),
            ]),
        ];
    }

    public function lovChoices(): array
    {
        $choices = [];
        foreach (self::LOV_ATTRIBUTES as $attribute) {
            $choices[$attribute] = LieuLovCatalog::choicesFor($attribute);
        }

        return $choices;
    }

    public function createAggregate(): object
    {
        return new Lieu();
    }

    public function findAggregateByFiche(Fiche $fiche): ?object
    {
        return $this->lieux->findOneByFicheWithLocalisation($fiche);
    }

    public function ficheOf(object $aggregate): Fiche
    {
        if (!$aggregate instanceof Lieu) {
            throw new \LogicException('Agrégat inattendu pour le type lieu.');
        }

        return $aggregate->fiche();
    }

    /** @return list<ColumnDefinition> */
    private function salleColumns(): array
    {
        return [
            new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
            new ColumnDefinition('superficie', ColumnKind::Int, 'superficie', help: 'en m²'),
            new ColumnDefinition('capacite_reunion', ColumnKind::Int, 'capaciteReunion'),
            new ColumnDefinition('capacite_u', ColumnKind::Int, 'capaciteU'),
            new ColumnDefinition('capacite_classe', ColumnKind::Int, 'capaciteClasse'),
            new ColumnDefinition('capacite_theatre', ColumnKind::Int, 'capaciteTheatre'),
            new ColumnDefinition('capacite_cabaret', ColumnKind::Int, 'capaciteCabaret'),
            new ColumnDefinition('capacite_banquet', ColumnKind::Int, 'capaciteBanquet'),
            new ColumnDefinition('capacite_cocktail', ColumnKind::Int, 'capaciteCocktail'),
            new ColumnDefinition('capacite_auditorium', ColumnKind::Int, 'capaciteAuditorium'),
            new ColumnDefinition('lumiere_jour', ColumnKind::Bool, 'lumiereJour', nullable: false),
            new ColumnDefinition('acces_pmr', ColumnKind::Bool, 'accesPmr', nullable: false),
            new ColumnDefinition('espace_dansant', ColumnKind::Bool, 'espaceDansant', nullable: false),
            new ColumnDefinition('climatisee', ColumnKind::Bool, 'climatisee', nullable: false),
        ];
    }
}
