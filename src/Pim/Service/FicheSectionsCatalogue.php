<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuFormCatalog;
use App\Pim\Geo\ZonesGeographiques;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Lov\ServiceLovCatalog;

/**
 * Les sections de l'éditeur de fiche, par gamme, alignées sur la maquette
 * front. Chaque section porte :
 *  - `champs`     : les champs de premier niveau du formulaire de la gamme rendus dans la section ;
 *  - `proprietes` : les propriétés racines de l'entité, pour rattacher la complétude ;
 *  - `blocs`      : les blocs spécialisés (medias, collaborateurs, sites, salesforce, suggestions, historique).
 *
 * Le rattachement fin aux champs de la maquette sera affiné avec le
 * dictionnaire des attributs ; cette ossature suit les blocs du back.
 * La gamme Traiteur (plateaux repas) est hors de cette version du MDM.
 */
final class FicheSectionsCatalogue
{
    /** Les six montants « à partir de » de l'onglet Tarifs du Restaurant (ordre maquette). */
    public const TARIFS_RESTAURANT = ['tarifDejeunerAssis', 'tarifCocktailDejeunatoire', 'tarifDinerAssis', 'tarifCocktailDinatoire', 'tarifForfaitVin', 'tarifForfaitAlcool'];

    /**
     * Une section peut porter des `cartes` (découpage visuel maquette) :
     * chaque carte = {titre: ?string, champs: list<string> (feuilles pointées
     * admises), colonnes: 2|3, pleins?: list<string>, bloc?: string,
     * conditions?: array<string, array{source: string, valeurs: string, vider?: bool}>}.
     * L'union des racines des champs des cartes est exactement `champs`
     * (test FicheSectionsCatalogueTest) : les consommateurs à plat
     * (champs omis, complétude, export) ne lisent jamais les cartes.
     *
     * @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string, cartes?: list<array<string, mixed>>}>
     */
    public static function pour(TypeFiche $type): array
    {
        return match ($type) {
            TypeFiche::Lieu => self::lieu(),
            TypeFiche::Restaurant => self::restaurant(),
            TypeFiche::Activite => self::activite(),
            TypeFiche::ServiceEvenementiel => self::service(),
            TypeFiche::Traiteur => [],
        };
    }

    /** @return array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string, cartes?: list<array<string, mixed>>} */
    public static function section(TypeFiche $type, int $index): array
    {
        return self::pour($type)[$index] ?? self::pour($type)[0];
    }

    public static function indexValide(TypeFiche $type, int $index): int
    {
        return array_key_exists($index, self::pour($type)) ? $index : 0;
    }

    /** Indice de la section portant un bloc donné (ex. « collaborateurs »). */
    public static function indexBloc(TypeFiche $type, string $bloc): int
    {
        foreach (self::pour($type) as $index => $section) {
            if (in_array($bloc, $section['blocs'], true)) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Les 16 onglets de la maquette portail (Place), dans son ordre, en
     * cartes déclaratives : un bloc par titre maquette. Un champ peut être
     * une feuille pointée (`groupe.champ`) : le gabarit la résout dans le
     * formulaire sans changer la structure de soumission. Les blocs
     * hébergement et salles de réunion s'ouvrent quand leur question Oui/Non
     * est à Oui (retour Clem, bible VERSION BP). Les fonctions back sans
     * onglet maquette rejoignent l'onglet cohérent (sites de diffusion →
     * Booster ma visibilité, notes Salesforce → RSE).
     *
     * @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe: string, cartes?: list<array<string, mixed>>}>
     */
    private static function lieu(): array
    {
        /** @var \Closure(string, string ...): list<string> $chemins */
        $chemins = static fn (string $groupe, string ...$champs): array => array_map(static fn (string $c): string => $groupe.'.'.$c, array_values($champs));
        $conditions = static function (string $source, string ...$champs): array {
            $conditions = [];
            foreach ($champs as $champ) {
                $conditions[$champ] = ['source' => $source, 'valeurs' => '1'];
            }

            return $conditions;
        };
        $hebergement = ['chambreNbTotal', 'chambreNbTotalSingle', 'chambreNbTotalTwin', 'chambreDouble', 'chambreCapaciteTotale', 'chambreDescGenerale'];
        $synthese = ['salleReunionNbTotal', 'salleReunionCapaciteMaxCocktail', 'salleReunionCapaciteMaxTheatre', 'salleReunionCapaciteMinTheatre', 'salleReunionSurfaceMinReunion', 'salleReunionSurfaceMaxReunion', 'salleReunionDescSalleSeminaire'];
        $restauration = ['restaurantTotal', 'restaurantSalleRestauration', 'restaurantCapaciteDebout', 'restaurantCapaciteAssis', 'restaurantSoireeDansante', 'restaurantCocktailDinatoire', 'restaurantTraiteurSurPlace', 'restaurantInterventionTraiteurExterne', 'restaurantTrateurExterneClient', 'restaurantPrivatisable', 'restaurantHeureInterruptionMusique', 'typeCuisine', 'serviceRestauration'];
        $rse = ['achatResponsable', 'impactEnv', 'impactSocial', 'volumeAchatCatEsatStpa', 'mobilite', 'certification'];
        $tarifs = static fn (string $titre, string ...$champs): array => ['titre' => $titre, 'champs' => $chemins('tarification', ...$champs), 'colonnes' => 3];

        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'generaleTypologie', 'informationsGenerales', 'generaleWebsiteUrl', 'businessPremium', 'partenaireBp', 'disponibilites', 'periodesFermeture'],
                'proprietes' => ['label', 'generaleTypologie', 'generaleWebsiteUrl', 'periodesFermeture', ...array_keys(LieuFormCatalog::general()), ...array_keys(LieuFormCatalog::availability())],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['label', 'generaleTypologie', ...$chemins('informationsGenerales', 'generaleChainesGroupeHot', 'evenementsPredilection', 'generaleEtabRp'), 'generaleWebsiteUrl', 'businessPremium', 'partenaireBp'], 'colonnes' => 2],
                    // Lieu privatisable, horaires par jour, périodes de fermeture (partial _disponibilites).
                    ['titre' => 'Disponibilités', 'champs' => ['disponibilites', 'periodesFermeture'], 'colonnes' => 2, 'bloc' => 'disponibilites_lieu'],
                ],
            ],
            [
                'titre' => 'Localisation & accessibilité',
                'champs' => ['localisation', 'acces', 'accessibiliteDescription.pmrAcces', 'accessibiliteDescription.pmrDetails'],
                'proprietes' => ['localisation', 'pmrAcces', 'pmrDetails', 'acces'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    self::carteLocalisation(),
                    ['titre' => 'Accessibilité', 'champs' => ['acces'], 'colonnes' => 2, 'bloc' => 'collection'],
                    ['titre' => 'Détails des accès PMR', 'champs' => $chemins('accessibiliteDescription', 'pmrAcces', 'pmrDetails'), 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Thématiques & ambiances',
                'champs' => $chemins('accessibiliteDescription', 'taThematique', 'taCadreEnv', 'taAmbiance'),
                'proprietes' => ['taThematique', 'taCadreEnv', 'taAmbiance'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => $chemins('accessibiliteDescription', 'taThematique', 'taCadreEnv', 'taAmbiance'), 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Description',
                'champs' => $chemins('accessibiliteDescription', 'descGenerale', 'atout1', 'atout2', 'atout3', 'atout4', 'atout5', 'descGeneralePointInteret'),
                'proprietes' => ['descGenerale', 'atout1', 'atout2', 'atout3', 'atout4', 'atout5', 'descGeneralePointInteret'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => $chemins('accessibiliteDescription', 'descGenerale', 'atout1', 'atout2', 'atout3', 'atout4', 'atout5', 'descGeneralePointInteret'), 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Hébergement',
                'champs' => ['hebergement'],
                'proprietes' => array_keys(LieuFormCatalog::accommodation()),
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    // La question ouvre le bloc (retour Clem) : le reste n'apparaît que si Oui.
                    ['titre' => null, 'champs' => $chemins('hebergement', 'chambreHebergement', ...$hebergement), 'colonnes' => 2, 'pleins' => ['hebergement.chambreHebergement'], 'conditions' => $conditions('hebergement.chambreHebergement', ...$hebergement)],
                ],
            ],
            [
                'titre' => 'Réunion',
                'champs' => ['syntheseSalles', 'salles'],
                'proprietes' => ['salles', ...array_keys(LieuFormCatalog::meetingRooms())],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => 'Salles de réunion', 'champs' => $chemins('syntheseSalles', 'salleReunionExist', ...$synthese), 'colonnes' => 2, 'pleins' => ['syntheseSalles.salleReunionExist'], 'conditions' => $conditions('syntheseSalles.salleReunionExist', ...$synthese)],
                    // Matrice des salles (mdm/fiche/_capacites), visible si des salles existent.
                    ['titre' => 'Capacités', 'champs' => ['salles'], 'colonnes' => 2, 'bloc' => 'salles', 'condition' => ['source' => 'syntheseSalles.salleReunionExist', 'valeurs' => '1']],
                ],
            ],
            [
                'titre' => 'Restauration',
                // La liaison vers la fiche Restaurant du lieu ouvre l'onglet.
                'champs' => ['restaurant', 'restauration'],
                'proprietes' => ['restaurant', ...array_keys(LieuFormCatalog::restaurant())],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['restaurant', ...$chemins('restauration', ...$restauration)], 'colonnes' => 2, 'pleins' => ['restaurant']],
                ],
            ],
            [
                'titre' => 'Loisirs & team building',
                'champs' => ['loisirs'],
                'proprietes' => array_keys(LieuFormCatalog::leisure()),
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => $chemins('loisirs', 'loisirInterne'), 'colonnes' => 2, 'pleins' => ['loisirs.loisirInterne']],
                    ['titre' => 'Team buildings', 'champs' => $chemins('loisirs', 'loisirExterneNomPresta', 'loisirExterneNomActivite'), 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Services & équipements',
                'champs' => ['equipementsServices'],
                'proprietes' => array_keys(LieuFormCatalog::equipmentAndServices()),
                // Rendu en groupes de puces cochables (partial mdm/fiche/_puces).
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['equipementsServices'], 'colonnes' => 2, 'bloc' => 'puces'],
                ],
            ],
            [
                'titre' => 'RSE',
                'champs' => ['rse'],
                'proprietes' => array_keys(LieuFormCatalog::rse()),
                // Les notes RSE Salesforce (lecture seule) accompagnent l'onglet.
                'blocs' => ['salesforce'],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => $chemins('rse', 'demarcheRse', ...[...$rse, 'rseDescGenerale']), 'colonnes' => 2, 'pleins' => ['rse.demarcheRse']],
                ],
            ],
            [
                'titre' => 'Tarifs',
                'champs' => ['tarification'],
                'proprietes' => ['tarification'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['intro' => 'Référencement en ligne : ciblez les événements sur lesquels vous souhaitez être visible. Indiquez vos tarifs "à partir de" pour nous permettre de qualifier au mieux les demandes.'] + $tarifs('Séminaire à la journée', 'seminaireJourneeDemiJourneeEtude', 'seminaireJourneeJourneeEtude', 'seminaireJourneeDemiJourneeEtudeCocktail', 'seminaireJourneeJourneeEtudeCocktail'),
                    $tarifs('Séminaire avec nuitée', 'seminaireNuiteeSemiResidentiel', 'seminaireNuiteeResidentiel', 'seminaireNuiteeResidentielAllInclusive'),
                    $tarifs('Location de salle seule', 'locSalleSeulDemiJournee', 'locSalleSeulJournee', 'locSalleSeulSoiree'),
                    $tarifs('Cocktail et soirées', 'csCocktailDejeunatoire10Pers', 'csCocktailDinatoire', 'csSoireeDansante', 'csSoireeDinerAssis'),
                    $tarifs('Restauration', 'tarifRestDejeunerAssis', 'tarifRestDinerAssis', 'tarifRestOptVin', 'tarifRestOptAlcool', 'tarifRestForfaitPersonalise'),
                    $tarifs('Hébergement groupe', 'hebergGroupTarifChambreSingle', 'hebergGroupTarifChambreTwin', 'hebergGroupTarifChambreDouble'),
                    ['titre' => 'Offre spéciale', 'champs' => $chemins('tarification', 'offreSpeciale', 'promotionDebut', 'promotionFin'), 'colonnes' => 2],
                ],
            ],
            [
                // Le lien vidéo (visibilite.generaleYoutube) est rendu dans
                // l'onglet interne Vidéo du volet (shell _medias_onglets),
                // rattaché au formulaire principal par form="form-fiche".
                'titre' => 'Médias',
                'champs' => [],
                'proprietes' => ['ressources', 'generaleYoutube'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                // Formules maquette (désactivées, pas d'entité back) + les
                // réglages réels de visibilité et les sites de diffusion.
                'titre' => 'Booster ma visibilité',
                'champs' => ['visibilite.miceStatut', 'visibilite.afficherContact'],
                'proprietes' => ['miceStatut', 'afficherContact'],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => 'Gérer sa visibilité', 'champs' => ['visibilite.miceStatut', 'visibilite.afficherContact'], 'colonnes' => 2],
                ],
            ],
            self::sectionFacturation(),
            [
                'titre' => 'Utilisateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
                'groupe' => 'parametres',
            ],
            [
                // Maquette pure (« rien ne se perd ») : pas de back pour les
                // templates de message — rendus désactivés avec infobulle.
                'titre' => 'Templates de message',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['templates'],
                'groupe' => 'parametres',
            ],
        ];
    }

    /**
     * Onglets de la maquette portail prestataire (2026-09) : les cartes
     * déclarent le découpage visuel de chaque onglet (titre, champs dans
     * l'ordre maquette, colonnes, bloc spécialisé). Les champs restent à plat
     * dans `champs`/`proprietes` (complétude, export, champs omis).
     *
     * @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string, cartes?: list<array<string, mixed>>}>
     */
    private static function restaurant(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement', 'siteOfficiel', 'lieu', 'businessPremium', 'partenaireBp', 'privatisationTotale', 'privatisationPartielle', 'joursOuverture', 'horairesJours', 'periodesFermeture'],
                'proprietes' => ['label', 'typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement', 'siteOfficiel', 'lieu', 'privatisationTotale', 'privatisationPartielle', 'joursOuverture', 'horairesJours', 'periodesFermeture'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['label', 'typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement', 'siteOfficiel', 'lieu', 'businessPremium', 'partenaireBp'], 'colonnes' => 2],
                    ['titre' => 'Disponibilités', 'champs' => ['privatisationTotale', 'privatisationPartielle', 'joursOuverture', 'horairesJours', 'periodesFermeture'], 'colonnes' => 2, 'bloc' => 'horaires'],
                ],
            ],
            [
                'titre' => 'Localisation & accessibilité',
                'champs' => ['localisation', 'acces', 'accesPmr', 'toilettesPmr'],
                'proprietes' => ['localisation', 'acces', 'accesPmr', 'toilettesPmr'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    self::carteLocalisation(),
                    ['titre' => 'Accessibilité', 'champs' => ['acces'], 'colonnes' => 2, 'bloc' => 'collection'],
                    // Les deux questions PMR toujours visibles (retour de relecture 2026-09-04).
                    ['titre' => 'Détails des accès PMR', 'champs' => ['accesPmr', 'toilettesPmr'], 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Description',
                'champs' => ['descriptionGenerale', 'atouts'],
                'proprietes' => ['descriptionGenerale', 'atouts'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['descriptionGenerale', 'atouts'], 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Capacités',
                'champs' => ['capaciteAssiseMax', 'capaciteEspacePrivatisable', 'capaciteBanquet', 'capaciteCocktail', 'salles'],
                'proprietes' => ['capaciteAssiseMax', 'capaciteEspacePrivatisable', 'capaciteBanquet', 'capaciteCocktail', 'salles'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => 'Capacité assise (Groupe)', 'champs' => ['capaciteAssiseMax', 'capaciteEspacePrivatisable', 'capaciteBanquet'], 'colonnes' => 3],
                    ['titre' => 'Capacité cocktail / debout', 'champs' => ['capaciteCocktail'], 'colonnes' => 3],
                    // Matrice des salles (mdm/fiche/_capacites), absente de la maquette, conservée.
                    ['titre' => 'Salles & espaces privatisables', 'champs' => ['salles'], 'colonnes' => 2, 'bloc' => 'salles'],
                ],
            ],
            [
                'titre' => 'Services & équipements',
                'champs' => ['services', 'equipements'],
                'proprietes' => ['services', 'equipements'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'RSE',
                'champs' => ['engagementsRse'],
                'proprietes' => ['engagementsRse'],
                // Les notes RSE Salesforce (lecture seule) accompagnent l'onglet.
                'blocs' => ['salesforce'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Tarifs',
                'champs' => self::TARIFS_RESTAURANT,
                'proprietes' => self::TARIFS_RESTAURANT,
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => self::TARIFS_RESTAURANT, 'colonnes' => 2, 'bloc' => 'tarifs_restaurant'],
                ],
            ],
            [
                // Champs de dépôt (menus, supports, titre/source) et lien
                // vidéo rendus dans les onglets internes du volet (shell
                // _medias_onglets), rattachés au formulaire principal par
                // form="form-fiche".
                'titre' => 'Médias',
                'champs' => [],
                'proprietes' => ['ressources', 'menus', 'youtubeUrl'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Booster ma visibilité',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            self::sectionFacturation(),
            [
                'titre' => 'Utilisateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
                'groupe' => 'parametres',
            ],
            [
                'titre' => 'Templates de message',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['templates'],
                'groupe' => 'parametres',
            ],
        ];
    }

    /**
     * Onglet « Facturation & partenariat » (groupe Paramètres), identique
     * pour les quatre gammes : le bloc FicheAdministratif (feuilles pointées
     * `administratif.*`) et les six pièces jointes en dropzone, dans les
     * cartes de la maquette portail. Les six fichiers sont des champs de
     * premier niveau non mappés (FicheFormCatalog::ajouterFichiers).
     *
     * @return array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe: string, cartes: list<array<string, mixed>>}
     */
    public static function sectionFacturation(): array
    {
        $a = static fn (string ...$champs): array => array_map(static fn (string $c): string => 'administratif.'.$c, $champs);
        $acomptes = [];
        for ($i = 1; $i <= 3; ++$i) {
            $acomptes[] = 'administratif.condPaieAccDate'.$i;
            $acomptes[] = 'administratif.condPaieAccPourcentage'.$i;
        }
        $annulation = $a(...array_map(static fn (int $i): string => 'condPaieAnnPourcentage'.$i, range(1, 9)));

        return [
            'titre' => 'Facturation & partenariat',
            'champs' => ['administratif', 'urssafFichier', 'rcProFichier', 'ribFichier', 'affacturageRibFichier', 'conventionFichier', 'cgvFichier'],
            'proprietes' => ['administratif'],
            'blocs' => [],
            'groupe' => 'parametres',
            'cartes' => [
                [
                    'titre' => 'Informations légales',
                    'champs' => [...$a('infoLegaleNom', 'infoLegaleFormeJuridique', 'infoLegaleRuePostal', 'infoLegaleAdresse2', 'infoLegaleCodePostal', 'infoLegaleVille', 'inforLegalePays', 'infoLegaleSiret', 'infoLegaleNumTva'), 'urssafFichier', 'rcProFichier', ...$a('infoLegaleAssujettiTva', 'infoLegaleTva', 'infoLegaleTypeDeProcedureJudiciaire')],
                    'colonnes' => 2,
                    'conditions' => ['infoLegaleTva' => ['source' => 'administratif.infoLegaleAssujettiTva', 'valeurs' => '1', 'vider' => true]],
                ],
                ['titre' => 'Adresse de facturation', 'champs' => $a('adresseFacturationNom', 'adresseFacturationNumTva', 'adresseFacturationRuePostal', 'adresseFacturationCodePostal', 'adresseFacturationVille', 'adresseFacturationPays'), 'colonnes' => 3],
                ['titre' => 'Contact de facturation', 'champs' => $a('contactFacturationNom', 'contactFacturationPrenom', 'contactFacturationEmail', 'contactFacturationTelephone'), 'colonnes' => 2],
                [
                    'titre' => 'Mode de paiements acceptés',
                    'champs' => [...$a('modePaiementBic', 'modePaiementIban'), 'ribFichier', ...$a('modePaiementAffacturage', 'affacturageBic', 'affacturageIban'), 'affacturageRibFichier', ...$a('modePaiementCarte', 'modePaiementCarteListe', 'modePaiementAcceptDeductionCom')],
                    'colonnes' => 2,
                    'conditions' => [
                        'affacturageBic' => ['source' => 'administratif.modePaiementAffacturage', 'valeurs' => '1', 'vider' => true],
                        'affacturageIban' => ['source' => 'administratif.modePaiementAffacturage', 'valeurs' => '1', 'vider' => true],
                        'affacturageRibFichier' => ['source' => 'administratif.modePaiementAffacturage', 'valeurs' => '1'],
                        'modePaiementCarteListe' => ['source' => 'administratif.modePaiementCarte', 'valeurs' => '1', 'vider' => true],
                    ],
                ],
                ['titre' => 'Conditions de paiement de l\'acompte', 'champs' => $acomptes, 'colonnes' => 2, 'bloc' => 'acomptes'],
                ['titre' => 'Conditions de paiement annulation', 'champs' => $annulation, 'colonnes' => 3, 'bloc' => 'annulation'],
                ['titre' => 'Paiement des soldes', 'champs' => $a('datePaiementSold'), 'colonnes' => 2],
                ['titre' => 'Commission', 'champs' => $a('commissionTaux', 'commissionPaiement', 'commissionApplicable'), 'colonnes' => 3],
                ['titre' => 'Convention de partenariat', 'champs' => [...$a('convPartSigneeLe', 'convPartTaux'), 'conventionFichier', ...$a('signataireEmail', 'signataireNom', 'signatairePrenom')], 'colonnes' => 2],
                ['titre' => 'Conditions générales de ventes', 'champs' => ['cgvFichier'], 'colonnes' => 2],
            ],
        ];
    }

    /**
     * Carte « Localisation » commune aux gammes alignées sur la maquette :
     * pays seul sur sa ligne, puis trois champs par ligne. Feuilles pointées
     * de LocalisationType, dont l'ordre interne (partagé avec le Lieu) ne
     * change pas.
     *
     * @return array<string, mixed>
     */
    public static function carteLocalisation(): array
    {
        return [
            'titre' => 'Localisation',
            'champs' => [
                'localisation.pays',
                'localisation.ruePostale', 'localisation.codePostal', 'localisation.ville',
                'localisation.arrondissement', 'localisation.departement', 'localisation.region',
                'localisation.latitude', 'localisation.longitude', 'localisation.countryCode',
            ],
            'colonnes' => 3,
            'pleins' => ['localisation.pays'],
        ];
    }

    /**
     * Onglets de la maquette portail prestataire (Activité) : Classification
     * fondue dans Informations générales (sous-thématiques affichées selon
     * les thématiques cochées), rayon d'action en radios pilotant les cartes
     * Localisation fixe / mobile, cinq « plus », durées en hh:mm, forfaits et
     * options en deux cartes de trois emplacements.
     *
     * @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string, cartes?: list<array<string, mixed>>}>
     */
    private static function activite(): array
    {
        // Sous-thématiques dans l'ordre maquette, chacune conditionnée à sa thématique parente.
        $sousThematiques = [];
        $conditions = [];
        foreach ([
            'TA_NAUTIQUE_AQUATIQUE_SS', 'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS', 'TA_CULINAIRE_OENOLOGIQUE_SS',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS', 'TA_DIGITAL_HIGH_TECH_SS', 'TA_SENSATION_SPORT_MECA_SS',
            'TA_SPORTIVE_LUDIQUE_SS', 'TA_NATURE_RSE_SS', 'TA_BIEN_ETRE_DETENTE_SS',
        ] as $attribut) {
            $champ = ActiviteLovCatalog::SOUS_THEMATIQUE_FIELDS[$attribut];
            $sousThematiques[] = $champ;
            $conditions[$champ] = ['source' => 'thematiques', 'valeurs' => ActiviteLovCatalog::thematiqueOf($attribut)];
        }

        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'prestataire', 'langues', 'thematiques', 'types', 'businessPremium', 'partenaireBp', ...$sousThematiques],
                'proprietes' => ['label', 'prestataire', 'langues', 'thematiques', 'types', 'sousThematiques'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    [
                        'titre' => null,
                        'champs' => ['label', 'prestataire', 'langues', 'thematiques', 'types', 'businessPremium', 'partenaireBp', ...$sousThematiques],
                        'colonnes' => 2,
                        'pleins' => $sousThematiques,
                        'conditions' => $conditions,
                    ],
                ],
            ],
            [
                'titre' => 'Localisation & accessibilité',
                'champs' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles', 'touteFrance'],
                'proprietes' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => 'Rayon d\'action géographique', 'champs' => ['modeIntervention'], 'colonnes' => 2, 'pleins' => ['modeIntervention']],
                    ['titre' => 'Localisation fixe', 'condition' => ['source' => 'modeIntervention', 'valeurs' => 'fixe']] + self::carteLocalisation(),
                    [
                        'titre' => 'Localisation mobile',
                        'champs' => ['paysMobiles', 'regionsMobiles', 'departementsMobiles', 'touteFrance'],
                        'colonnes' => 2,
                        'pleins' => ['departementsMobiles'],
                        'condition' => ['source' => 'modeIntervention', 'valeurs' => 'mobile'],
                        // Pays → régions → départements (référentiel ZonesGeographiques).
                        'attributs' => ZonesGeographiques::attributsStimulus(),
                    ],
                ],
            ],
            [
                'titre' => 'Description',
                'champs' => ['descriptionGenerale', 'comprendPrestation', 'objectifs', 'plus'],
                'proprietes' => ['descriptionGenerale', 'comprendPrestation', 'objectifs', 'plus'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['descriptionGenerale', 'comprendPrestation', 'objectifs'], 'colonnes' => 2],
                    ['titre' => 'Les plus', 'champs' => ['plus'], 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Capacités',
                'champs' => ['participantsMin', 'participantsMax', 'dureeMinMinutes', 'dureeMaxMinutes'],
                'proprietes' => ['participantsMin', 'participantsMax', 'dureeMinMinutes', 'dureeMaxMinutes'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => 'Capacité globale', 'champs' => ['participantsMin', 'participantsMax'], 'colonnes' => 2],
                    ['titre' => 'Durée de l\'activité / Séminaire', 'champs' => ['dureeMinMinutes', 'dureeMaxMinutes'], 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'RSE',
                'champs' => ['engagementsRse'],
                'proprietes' => ['engagementsRse'],
                'blocs' => ['salesforce'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Tarifs',
                'champs' => ['tarifParPersonne', 'offres'],
                'proprietes' => ['tarifParPersonne', 'offres'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => 'Mes tarifs', 'champs' => ['tarifParPersonne'], 'colonnes' => 3],
                    ['titre' => 'Forfaits', 'champs' => ['offres'], 'colonnes' => 2, 'bloc' => 'offres_activite', 'type_offre' => 'forfait'],
                    ['titre' => 'Options', 'champs' => ['offres'], 'colonnes' => 2, 'bloc' => 'offres_activite', 'type_offre' => 'option'],
                ],
            ],
            [
                // Champs de dépôt et lien vidéo rendus dans les onglets
                // internes du volet (shell _medias_onglets).
                'titre' => 'Médias',
                'champs' => [],
                'proprietes' => ['ressources', 'youtubeUrl'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Booster ma visibilité',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            self::sectionFacturation(),
            [
                'titre' => 'Utilisateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
                'groupe' => 'parametres',
            ],
            [
                'titre' => 'Templates de message',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['templates'],
                'groupe' => 'parametres',
            ],
        ];
    }

    /**
     * Onglets de la maquette portail prestataire (Service événementiel) :
     * Description, Prestation & accessibilité et RSE fondus dans
     * Informations générales, onglet Prestations créé pour les
     * sous-prestations, accès + PMR ajoutés à Localisation & accessibilité.
     *
     * @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string, cartes?: list<array<string, mixed>>}>
     */
    private static function service(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'prestataireEsat', 'demarcheRse', 'businessPremium', 'partenaireBp', 'descriptionGenerale', 'adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'participantsMin', 'participantsMax', 'dureeMinutes', 'materielInclus', 'contraintesLogistiques', 'equipementParticipantsRequis', 'equipementReceptionRequis'],
                'proprietes' => ['label', 'prestataireEsat', 'demarcheRse', 'descriptionGenerale', 'adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'participantsMin', 'participantsMax', 'dureeMinutes', 'materielInclus', 'contraintesLogistiques', 'equipementParticipantsRequis', 'equipementReceptionRequis'],
                // Les notes RSE Salesforce (lecture seule) suivent l'onglet RSE fondu ici.
                'blocs' => ['salesforce'],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['label', 'prestataireEsat', 'demarcheRse', 'businessPremium', 'partenaireBp'], 'colonnes' => 3, 'pleins' => ['label']],
                    ['titre' => 'Description générale', 'champs' => ['descriptionGenerale'], 'colonnes' => 2],
                    ['titre' => 'Prestations', 'champs' => ['adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'participantsMin', 'participantsMax', 'dureeMinutes'], 'colonnes' => 3],
                    ['titre' => 'Matériel', 'champs' => ['materielInclus', 'contraintesLogistiques', 'equipementParticipantsRequis', 'equipementReceptionRequis'], 'colonnes' => 3],
                ],
            ],
            [
                'titre' => 'Localisation & accessibilité',
                'champs' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles', 'acces', 'accesPmr', 'materielAdaptePmr'],
                'proprietes' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles', 'acces', 'accesPmr', 'materielAdaptePmr'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    // Le rayon d'action (MDM) ouvre la carte, puis l'adresse maquette.
                    ['titre' => 'Localisation', 'champs' => ['modeIntervention', ...self::carteLocalisation()['champs']], 'colonnes' => 3, 'pleins' => ['modeIntervention', 'localisation.pays']],
                    // Pays → régions → départements, comme la Localisation mobile de l'Activité.
                    ['titre' => 'Zone d\'intervention principale', 'champs' => ['paysMobiles', 'regionsMobiles', 'departementsMobiles'], 'colonnes' => 2, 'pleins' => ['departementsMobiles'], 'attributs' => ZonesGeographiques::attributsStimulus()],
                    ['titre' => 'Accessibilité', 'champs' => ['acces'], 'colonnes' => 2, 'bloc' => 'collection'],
                    ['titre' => 'Détails des accès PMR', 'champs' => ['accesPmr', 'materielAdaptePmr'], 'colonnes' => 2],
                ],
            ],
            [
                'titre' => 'Prestations',
                'champs' => ['prestations', ...array_values(ServiceLovCatalog::SOUS_PRESTATION_FIELDS)],
                'proprietes' => ['prestations', 'sousPrestations'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['prestations', ...array_values(ServiceLovCatalog::SOUS_PRESTATION_FIELDS)], 'colonnes' => 2, 'pleins' => ['prestations']],
                ],
            ],
            [
                'titre' => 'Tarifs',
                'champs' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'],
                'proprietes' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
                'cartes' => [
                    ['titre' => null, 'champs' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'], 'colonnes' => 3],
                ],
            ],
            [
                // Champs de dépôt et lien vidéo rendus dans les onglets
                // internes du volet (shell _medias_onglets).
                'titre' => 'Médias',
                'champs' => [],
                'proprietes' => ['ressources', 'youtubeUrl'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Booster ma visibilité',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            self::sectionFacturation(),
            [
                'titre' => 'Utilisateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
                'groupe' => 'parametres',
            ],
            [
                'titre' => 'Templates de message',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['templates'],
                'groupe' => 'parametres',
            ],
        ];
    }
}
