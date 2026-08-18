<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Lov\LieuLovCatalog;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;

final class LieuFormCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function general(): array
    {
        return [
            // Ordre de la maquette : groupe, événements, puis ERP.
            'generaleChainesGroupeHot' => self::choice('GENERALE_CHAINES_GROUPE_HOT', 'Groupe et chaîne hôtelière', true),
            'evenementsPredilection' => self::choice('GENERALE_EVENEMENTS_PREDILECTION', 'Événements de prédilection', true),
            'generaleEtabRp' => ['label' => 'ERP'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function availability(): array
    {
        $hour = ['type' => IntegerType::class, 'options' => ['constraints' => [new Range(min: 0, max: 23)]]];
        $minute = ['type' => IntegerType::class, 'options' => ['constraints' => [new Range(min: 0, max: 59)]]];

        return [
            'dispoLieuPrivatisable' => ['label' => 'Lieu privatisable'],
            'joursOuverture' => self::choice('DISPO_JOUR_OUVERTURE', "Jours d'ouverture", true, etendu: true),
            'dispoHeureOuvertureHeure' => $hour + ['label' => "Heure d'ouverture"],
            'dispoHeureOuvertureMinutes' => $minute + ['label' => "Minutes d'ouverture"],
            'dispoHeureFermetureHeure' => $hour + ['label' => 'Heure de fermeture'],
            'dispoHeureFermetureMinutes' => $minute + ['label' => 'Minutes de fermeture'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function accessibilityAndDescription(): array
    {
        return [
            'descGeneralePointInteret' => ['label' => "Points d'intérêt à moins de 15 min à pied", 'type' => TextareaType::class],
            'pmrAcces' => ['label' => 'Accès PMR'],
            'pmrDetails' => ['label' => 'Détails des accès PMR', 'type' => TextareaType::class],
            'taThematique' => self::choice('TA_THEMATIQUE', 'Thématique', true),
            'taCadreEnv' => self::choice('TA_CADRE_ENV', 'Cadre / environnement', true),
            'taAmbiance' => self::choice('TA_AMBIANCE', 'Ambiance', true),
            'descGenerale' => self::richText('Description générale'),
            'atout1' => ['label' => 'Plus n°1'],
            'atout2' => ['label' => 'Plus n°2'],
            'atout3' => ['label' => 'Plus n°3'],
            'atout4' => ['label' => 'Plus n°4'],
            'atout5' => ['label' => 'Plus n°5'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function accommodation(): array
    {
        return self::labels([
            'chambreHebergement' => "L'établissement dispose d'hébergement",
            'chambreNbTotal' => 'Nombre total de chambres',
            'chambreNbTotalSingle' => 'Nombre de chambres single',
            'chambreNbTotalTwin' => 'Nombre de chambres twin',
            'chambreDouble' => 'Nombre de chambres doubles',
            'chambreCapaciteTotale' => "Capacité totale d'hébergement",
            'chambreDescGenerale' => 'Description de l’hébergement',
        ], ['chambreDescGenerale']);
    }

    /** @return array<string, array<string, mixed>> */
    public static function meetingRooms(): array
    {
        return self::labels([
            'salleReunionExist' => "L'établissement dispose de salles de réunion",
            'salleReunionNbTotal' => 'Nombre de salles de réunion',
            'salleReunionCapaciteMaxCocktail' => 'Capacité maximale cocktail',
            'salleReunionCapaciteMaxTheatre' => 'Capacité maximale théâtre',
            'salleReunionCapaciteMinTheatre' => 'Capacité minimale théâtre',
            'salleReunionSurfaceMinReunion' => 'Surface minimale de réunion',
            'salleReunionSurfaceMaxReunion' => 'Surface maximale de réunion',
        ]) + [
            'salleReunionDescSalleSeminaire' => self::richText('Description des salles de séminaire'),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function equipmentAndServices(): array
    {
        // Étendus (cases) : la section les rend en puces cochables (maquette).
        return [
            'equipements' => self::choice('EQUIPEMENTS', 'Équipements', true, etendu: true),
            'services' => self::choice('SERVICES', 'Services', true, etendu: true),
            'techniqueReunion' => self::choice('TECHNIQUE_REUNION', 'Technique et réunion', true, etendu: true),
            'installation' => self::choice('INSTALLATION', 'Installations', true, etendu: true),
            'bienEtre' => self::choice('BIEN_ETRE', 'Bien-être', true, etendu: true),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function rse(): array
    {
        return [
            'demarcheRse' => ['label' => 'Démarche RSE'],
            'rseDescGenerale' => ['label' => 'Descriptif complet des engagements RSE', 'type' => TextareaType::class],
            'achatResponsable' => self::choice('ACHAT_RESPONSABLE', 'Achats responsables', true),
            'impactEnv' => self::choice('IMPACT_ENV', 'Impact environnemental', true),
            'impactSocial' => self::choice('IMPACT_SOCIAL', 'Impact social', true),
            'volumeAchatCatEsatStpa' => self::choice('VOLUME_ACHAT_CAT_ESAT_STPA', '% d’achats ESAT/STPA', true),
            'mobilite' => self::choice('MOBILITE', 'Mobilité', true),
            'certification' => self::choice('CERTIFICATION', 'Certifications', true),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function leisure(): array
    {
        return self::labels([
            'loisirInterne' => "Loisirs internes à l'établissement",
            'loisirExterneNomPresta' => 'Prestataires externes',
            'loisirExterneNomActivite' => 'Activités externes',
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function restaurant(): array
    {
        return self::labels([
            'restaurantTotal' => 'Nombre de restaurants sur place',
            'restaurantSalleRestauration' => 'Nombre de salles de restauration',
            'restaurantCapaciteDebout' => 'Capacité maximale debout',
            'restaurantCapaciteAssis' => 'Capacité maximale assise',
            'restaurantSoireeDansante' => 'Soirée dansante',
            'restaurantCocktailDinatoire' => 'Cocktail dînatoire',
            'restaurantTraiteurSurPlace' => 'Traiteur sur place',
            'restaurantInterventionTraiteurExterne' => 'Gestion d’un traiteur externe',
            'restaurantTrateurExterneClient' => 'Traiteur externe autorisé',
            'restaurantPrivatisable' => 'Restaurant privatisable',
            'restaurantHeureInterruptionMusique' => 'Heure limite de diffusion de la musique',
        ]) + [
            'typeCuisine' => self::choice('TYPE_CUISINE', 'Types de cuisine', true),
            'serviceRestauration' => self::choice('SERVICE_RESTAURATION', 'Services de restauration', true),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function visibility(): array
    {
        return [
            'generaleYoutube' => ['label' => 'Lien YouTube', 'type' => UrlType::class, 'options' => ['constraints' => [new Url(requireTld: true)]]],
            'sitePremium' => self::choice('SITE_PREMIUM', 'Activation des sites premium', true),
            'miceStatut' => self::choice('MICE_STATUT', 'Statut Premium'),
            'afficherContact' => ['label' => 'Afficher le contact'],
            'modePaiementCarteListe' => self::choice('MODE_PAIEMENT_CARTE_LISTE', 'Cartes bancaires acceptées', true),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function administrative(): array
    {
        $fields = self::labels([
            'infoLegaleNom' => 'Raison sociale', 'infoLegaleFormeJuridique' => 'Forme juridique',
            'infoLegaleRuePostal' => 'Rue postale', 'infoLegaleAdresse2' => "Complément d'adresse",
            'infoLegaleCodePostal' => 'Code postal', 'infoLegaleVille' => 'Ville', 'inforLegalePays' => 'Pays',
            'infoLegaleSiret' => 'N° de SIRET', 'infoLegaleNumTva' => 'N° de TVA',
            'infoLegaleAssujettiTva' => 'Assujetti à la TVA', 'infoLegaleTypeDeProcedureJudiciaire' => 'Procédure judiciaire',
            'adresseFacturationNom' => 'Nom de facturation', 'adresseFacturationRuePostal' => 'Rue de facturation',
            'adresseFacturationCodePostal' => 'Code postal de facturation', 'adresseFacturationVille' => 'Ville de facturation',
            'adresseFacturationPays' => 'Pays de facturation', 'adresseFacturationNumTva' => 'TVA de facturation',
            'contactFacturationNom' => 'Nom du contact de facturation', 'contactFacturationPrenom' => 'Prénom du contact de facturation',
            'contactFacturationEmail' => 'Email de facturation', 'contactFacturationTelephone' => 'Téléphone de facturation',
            'modePaiementBic' => 'BIC', 'modePaiementIban' => 'IBAN',
            'modePaiementAcceptDeductionCom' => 'Accepte la déduction de commission', 'modePaiementAffacturage' => 'Affacturage',
            'affacturageBic' => 'BIC d’affacturage', 'affacturageIban' => 'IBAN d’affacturage',
            'commissionApplicable' => 'Champ d’application de la commission',
            'convPartSigneeLe' => 'Convention signée le', 'convPartTaux' => 'Taux de commission',
            'signataireEmail' => 'Email du signataire', 'signatairePrenom' => 'Prénom du signataire', 'signataireNom' => 'Nom du signataire',
        ]);
        $fields['infoLegaleTva'] = self::choice('INFO_LEGALE_TVA', 'TVA au débit ou à l’encaissement');
        $fields['condPaieAccSignature'] = self::choice('COND_PAIE_ACC_SIGNATURE', 'Conditions de paiement de l’acompte');
        $fields['condPaieAnnSignature'] = self::choice('COND_PAIE_ANN_SIGNATURE', 'Conditions d’annulation');
        $fields['datePaiementSold'] = self::choice('DATE_PAIEMENT_SOLD', 'Date du paiement du solde');

        return $fields;
    }

    /** @return array<string, array<string, mixed>> */
    public static function pricing(): array
    {
        return self::labels([
            'seminaireJourneeDemiJourneeEtude' => 'Demi-journée étude',
            'seminaireJourneeJourneeEtude' => 'Journée d’étude',
            'seminaireJourneeDemiJourneeEtudeCocktail' => 'Demi-journée étude + cocktail',
            'seminaireJourneeJourneeEtudeCocktail' => 'Journée d’étude + cocktail',
            'seminaireNuiteeSemiResidentiel' => 'Séminaire semi-résidentiel',
            'seminaireNuiteeResidentiel' => 'Séminaire résidentiel',
            'seminaireNuiteeResidentielAllInclusive' => 'Séminaire résidentiel all inclusive',
            'locSalleSeulDemiJournee' => 'Location salle demi-journée', 'locSalleSeulJournee' => 'Location salle journée',
            'locSalleSeulSoiree' => 'Location salle soirée', 'csCocktailDejeunatoire10Pers' => 'Cocktail déjeunatoire 10 personnes',
            'csCocktailDinatoire' => 'Cocktail dînatoire', 'csSoireeDansante' => 'Soirée dansante',
            'csSoireeDinerAssis' => 'Soirée dîner assis', 'tarifRestDejeunerAssis' => 'Déjeuner assis',
            'tarifRestDinerAssis' => 'Dîner assis', 'tarifRestOptVin' => 'Option vin',
            'tarifRestOptAlcool' => 'Option alcool', 'tarifRestForfaitPersonalise' => 'Forfait personnalisé',
            'hebergGroupTarifChambreSingle' => 'Tarif chambre single', 'hebergGroupTarifChambreTwin' => 'Tarif chambre twin',
            'hebergGroupTarifChambreDouble' => 'Tarif chambre double',
        ]) + [
            'offreSpeciale' => ['label' => 'Offre spéciale', 'type' => TextareaType::class],
            'promotionDebut' => ['label' => 'Début de la promotion', 'type' => DateType::class, 'options' => ['widget' => 'single_text', 'input' => 'datetime_immutable']],
            'promotionFin' => ['label' => 'Fin de la promotion', 'type' => DateType::class, 'options' => ['widget' => 'single_text', 'input' => 'datetime_immutable']],
        ];
    }

    /**
     * @param array<string, string> $labels
     * @param list<string> $textareas
     * @return array<string, array<string, mixed>>
     */
    private static function labels(array $labels, array $textareas = []): array
    {
        $result = [];
        foreach ($labels as $name => $label) {
            $result[$name] = ['label' => $label];
            if (in_array($name, $textareas, true)) {
                $result[$name]['type'] = TextareaType::class;
            }
        }

        return $result;
    }

    /**
     * Champ de texte long enrichi : rendu par le composant Wysiwyg (TinyMCE) via
     * le drapeau `data-wysiwyg` que le thème de formulaire lit sur la zone de texte.
     *
     * @return array<string, mixed>
     */
    private static function richText(string $label): array
    {
        return ['label' => $label, 'type' => TextareaType::class, 'options' => ['attr' => ['data-wysiwyg' => true]]];
    }

    /** @return array<string, mixed> */
    private static function choice(string $code, string $label, bool $multiple = false, bool $etendu = false): array
    {
        return [
            'label' => $label,
            'type' => ChoiceType::class,
            'options' => [
                'choices' => array_flip(LieuLovCatalog::choicesFor($code)),
                'multiple' => $multiple,
                'expanded' => $etendu,
                'placeholder' => $multiple ? null : 'Non renseigné',
            ],
        ];
    }
}
