<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuFormCatalog;
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

    /** @return array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>} */
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
     * Les 16 onglets de la maquette, dans son ordre. Un champ peut être une
     * feuille pointée (`groupe.champ`) : le gabarit la résout dans le
     * formulaire sans changer la structure de soumission — c'est ainsi que le
     * groupe accessibiliteDescription se répartit sur trois onglets maquette.
     * Les fonctions back sans onglet maquette rejoignent l'onglet cohérent :
     * disponibilités → Informations générales (carte annexe maquette),
     * sites de diffusion + visibilité → Booster ma visibilité,
     * données Salesforce (notes RSE) → RSE ; l'extraction OCR vit au pied.
     *
     * @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe: string}>
     */
    private static function lieu(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'generaleTypologie', 'informationsGenerales', 'generaleWebsiteUrl', 'businessPremium', 'partenaireBp', 'disponibilites', 'periodesFermeture'],
                'proprietes' => ['label', 'generaleTypologie', 'generaleWebsiteUrl', 'periodesFermeture', ...array_keys(LieuFormCatalog::general()), ...array_keys(LieuFormCatalog::availability())],
                'blocs' => ['disponibilites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Localisation & accessibilité',
                // Accessibilité PMR avant la collection Accès (ordre maquette).
                'champs' => ['localisation', 'accessibiliteDescription.pmrAcces', 'accessibiliteDescription.pmrDetails', 'acces'],
                'proprietes' => ['localisation', 'pmrAcces', 'pmrDetails', 'acces'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Thématiques & ambiances',
                'champs' => ['accessibiliteDescription.taThematique', 'accessibiliteDescription.taCadreEnv', 'accessibiliteDescription.taAmbiance'],
                'proprietes' => ['taThematique', 'taCadreEnv', 'taAmbiance'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Description',
                'champs' => ['accessibiliteDescription.descGenerale', 'accessibiliteDescription.atout1', 'accessibiliteDescription.atout2', 'accessibiliteDescription.atout3', 'accessibiliteDescription.atout4', 'accessibiliteDescription.atout5', 'accessibiliteDescription.descGeneralePointInteret'],
                'proprietes' => ['descGenerale', 'atout1', 'atout2', 'atout3', 'atout4', 'atout5', 'descGeneralePointInteret'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Hébergement',
                'champs' => ['hebergement'],
                'proprietes' => array_keys(LieuFormCatalog::accommodation()),
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Réunion',
                'champs' => ['syntheseSalles', 'salles'],
                'proprietes' => ['salles', ...array_keys(LieuFormCatalog::meetingRooms())],
                // La collection des salles se rend en matrice (mdm/fiche/_capacites).
                'blocs' => ['capacites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Restauration',
                // La liaison vers la fiche Restaurant du lieu ouvre l'onglet.
                'champs' => ['restaurant', 'restauration'],
                'proprietes' => ['restaurant', ...array_keys(LieuFormCatalog::restaurant())],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Loisirs & team building',
                'champs' => ['loisirs'],
                'proprietes' => array_keys(LieuFormCatalog::leisure()),
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Services & équipements',
                'champs' => ['equipementsServices'],
                'proprietes' => array_keys(LieuFormCatalog::equipmentAndServices()),
                // Rendu en groupes de puces cochables (partial mdm/fiche/_puces).
                'blocs' => ['puces'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'RSE',
                'champs' => ['rse'],
                'proprietes' => array_keys(LieuFormCatalog::rse()),
                // Les notes RSE Salesforce (lecture seule) accompagnent l'onglet.
                'blocs' => ['salesforce'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Tarifs',
                'champs' => ['tarification'],
                'proprietes' => ['tarification'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
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
                // Feuilles pointées : generaleYoutube vit dans Médias › Vidéo.
                'titre' => 'Booster ma visibilité',
                'champs' => ['visibilite.miceStatut', 'visibilite.afficherContact', 'visibilite.modePaiementCarteListe'],
                'proprietes' => ['miceStatut', 'afficherContact', 'modePaiementCarteListe'],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Facturation & partenariat',
                'champs' => ['administratif'],
                'proprietes' => ['administratif'],
                'blocs' => [],
                'groupe' => 'parametres',
            ],
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
                    [
                        'titre' => 'Détails des accès PMR',
                        'champs' => ['accesPmr', 'toilettesPmr'],
                        'colonnes' => 2,
                        // Toilettes PMR : visible seulement si Accès PMR = Oui (maquette).
                        'conditions' => ['toilettesPmr' => ['source' => 'accesPmr', 'valeurs' => '1', 'vider' => true]],
                    ],
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

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string}> */
    private static function activite(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'prestataire', 'types', 'langues', 'businessPremium', 'partenaireBp'],
                'proprietes' => ['label', 'prestataire', 'types', 'langues'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Localisation & zone d\'intervention',
                'champs' => ['modeIntervention', 'localisation', 'touteFrance', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'proprietes' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Classification',
                'champs' => ['thematiques', ...array_values(ActiviteLovCatalog::SOUS_THEMATIQUE_FIELDS)],
                'proprietes' => ['thematiques', 'sousThematiques'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Description',
                'champs' => ['descriptionGenerale', 'comprendPrestation', 'objectifs', 'plus'],
                'proprietes' => ['descriptionGenerale', 'comprendPrestation', 'objectifs', 'plus'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Capacités & durées',
                'champs' => ['participantsMin', 'participantsMax', 'dureeMinMinutes', 'dureeMaxMinutes'],
                'proprietes' => ['participantsMin', 'participantsMax', 'dureeMinMinutes', 'dureeMaxMinutes'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
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

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string}> */
    private static function service(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'prestations', ...array_values(ServiceLovCatalog::SOUS_PRESTATION_FIELDS), 'businessPremium', 'partenaireBp', 'prestataireEsat'],
                'proprietes' => ['label', 'prestations', 'sousPrestations', 'prestataireEsat'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Localisation & zone d\'intervention',
                'champs' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'proprietes' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Prestation & accessibilité',
                'champs' => ['participantsMin', 'participantsMax', 'dureeMinutes', 'adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'materielInclus', 'equipementParticipantsRequis', 'equipementReceptionRequis', 'contraintesLogistiques'],
                'proprietes' => ['participantsMin', 'participantsMax', 'dureeMinutes', 'adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'materielInclus', 'equipementParticipantsRequis', 'equipementReceptionRequis', 'contraintesLogistiques'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Description',
                'champs' => ['descriptionGenerale'],
                'proprietes' => ['descriptionGenerale'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'RSE',
                'champs' => ['demarcheRse'],
                'proprietes' => ['demarcheRse'],
                'blocs' => ['salesforce'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Tarifs',
                'champs' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'],
                'proprietes' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
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
