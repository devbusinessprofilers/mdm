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
    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string}> */
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
                'champs' => ['label', 'generaleTypologie', 'informationsGenerales', 'generaleWebsiteUrl', 'telephone', 'businessPremium', 'partenaireBp', 'disponibilites', 'periodesFermeture'],
                // Les quatre horaires globaux restent des propriétés de la
                // section (dérivés des horaires par jour, complétude/audit).
                'proprietes' => ['label', 'generaleTypologie', 'generaleWebsiteUrl', 'periodesFermeture', ...array_keys(LieuFormCatalog::general()), ...array_keys(LieuFormCatalog::availability()), 'dispoHeureOuvertureHeure', 'dispoHeureOuvertureMinutes', 'dispoHeureFermetureHeure', 'dispoHeureFermetureMinutes'],
                'blocs' => ['disponibilites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Localisation & accessibilité',
                'champs' => ['localisation', 'acces', 'accessibiliteDescription.pmrAcces', 'accessibiliteDescription.pmrDetails'],
                'proprietes' => ['localisation', 'acces', 'pmrAcces', 'pmrDetails'],
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
                'champs' => ['restauration'],
                'proprietes' => array_keys(LieuFormCatalog::restaurant()),
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
                'titre' => 'Médias',
                'champs' => [],
                'proprietes' => ['ressources'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                // Formules maquette (désactivées, pas d'entité back) + les
                // réglages réels de visibilité et les sites de diffusion.
                'titre' => 'Booster ma visibilité',
                'champs' => ['visibilite'],
                'proprietes' => array_keys(LieuFormCatalog::visibility()),
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
                'titre' => 'Collaborateurs',
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

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>, groupe?: string}> */
    private static function restaurant(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'siteOfficiel', 'telephone', 'businessPremium', 'partenaireBp', 'privatisationTotale', 'privatisationPartielle', 'heureOuverture', 'heureFermeture', 'joursOuverture', 'periodesFermeture'],
                'proprietes' => ['label', 'siteOfficiel', 'privatisationTotale', 'privatisationPartielle', 'heureOuverture', 'heureFermeture', 'joursOuverture', 'periodesFermeture'],
                'blocs' => ['disponibilites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Localisation & accessibilité',
                'champs' => ['localisation', 'acces', 'accesPmr', 'toilettesPmr'],
                'proprietes' => ['localisation', 'acces', 'accesPmr', 'toilettesPmr'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Classification',
                'champs' => ['typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement'],
                'proprietes' => ['typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Description',
                'champs' => ['descriptionGenerale', 'atouts'],
                'proprietes' => ['descriptionGenerale', 'atouts'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Salles & capacités',
                'champs' => ['salles'],
                'proprietes' => ['salles'],
                'blocs' => [],
                'groupe' => 'ma_fiche',
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
                'titre' => 'Médias & menus',
                'champs' => ['menus', 'supportsCommerciaux', 'documentTitle', 'documentSource'],
                'proprietes' => ['ressources', 'menus'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Booster ma visibilité',
                'champs' => ['youtubeUrl'],
                'proprietes' => ['youtubeUrl'],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Collaborateurs',
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
    private static function activite(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'prestataire', 'types', 'langues', 'telephone', 'businessPremium', 'partenaireBp'],
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
                'titre' => 'Médias',
                'champs' => ['supportsCommerciaux', 'supportTitle', 'supportSource'],
                'proprietes' => ['ressources'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Booster ma visibilité',
                'champs' => ['youtubeUrl'],
                'proprietes' => ['youtubeUrl'],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Collaborateurs',
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
                'champs' => ['label', 'prestations', ...array_values(ServiceLovCatalog::SOUS_PRESTATION_FIELDS), 'telephone', 'businessPremium', 'partenaireBp', 'prestataireEsat'],
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
                'titre' => 'Médias',
                'champs' => ['supportsCommerciaux', 'supportTitle', 'supportSource'],
                'proprietes' => ['ressources'],
                'blocs' => ['medias'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Booster ma visibilité',
                'champs' => ['youtubeUrl'],
                'proprietes' => ['youtubeUrl'],
                'blocs' => ['formules', 'sites'],
                'groupe' => 'ma_fiche',
            ],
            [
                'titre' => 'Collaborateurs',
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
