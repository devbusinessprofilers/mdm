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
    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>}> */
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

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>}> */
    private static function lieu(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'businessPremium', 'partenaireBp', 'telephone', 'generaleTypologie', 'generaleWebsiteUrl', 'informationsGenerales'],
                'proprietes' => ['label', 'generaleTypologie', 'generaleWebsiteUrl', ...array_keys(LieuFormCatalog::general())],
                'blocs' => ['suggestions_attente'],
            ],
            [
                'titre' => 'Localisation & accès',
                'champs' => ['localisation', 'acces'],
                'proprietes' => ['localisation', 'acces'],
                'blocs' => [],
            ],
            [
                'titre' => 'Descriptif',
                'champs' => ['accessibiliteDescription'],
                'proprietes' => array_keys(LieuFormCatalog::accessibilityAndDescription()),
                'blocs' => [],
            ],
            [
                'titre' => 'Hébergement',
                'champs' => ['hebergement'],
                'proprietes' => array_keys(LieuFormCatalog::accommodation()),
                'blocs' => [],
            ],
            [
                'titre' => 'Restauration',
                'champs' => ['restauration'],
                'proprietes' => array_keys(LieuFormCatalog::restaurant()),
                'blocs' => [],
            ],
            [
                'titre' => 'Salles & capacités',
                'champs' => ['syntheseSalles', 'salles'],
                'proprietes' => ['salles', ...array_keys(LieuFormCatalog::meetingRooms())],
                'blocs' => [],
            ],
            [
                'titre' => 'Services & équipements',
                'champs' => ['equipementsServices'],
                'proprietes' => array_keys(LieuFormCatalog::equipmentAndServices()),
                'blocs' => [],
            ],
            [
                'titre' => 'Engagements RSE',
                'champs' => ['rse'],
                'proprietes' => array_keys(LieuFormCatalog::rse()),
                'blocs' => [],
            ],
            [
                'titre' => 'Loisirs & bien-être',
                'champs' => ['loisirs'],
                'proprietes' => array_keys(LieuFormCatalog::leisure()),
                'blocs' => [],
            ],
            [
                'titre' => 'Tarifs & formules',
                'champs' => ['tarification'],
                'proprietes' => ['tarification'],
                'blocs' => [],
            ],
            [
                'titre' => 'Administratif',
                'champs' => ['administratif'],
                'proprietes' => ['administratif'],
                'blocs' => [],
            ],
            [
                'titre' => 'Médias',
                'champs' => [],
                'proprietes' => ['ressources'],
                'blocs' => ['medias'],
            ],
            [
                'titre' => 'Disponibilités & fermetures',
                'champs' => ['disponibilites', 'periodesFermeture'],
                'proprietes' => ['periodesFermeture', ...array_keys(LieuFormCatalog::availability())],
                'blocs' => [],
            ],
            [
                'titre' => 'Collaborateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
            ],
            [
                'titre' => 'Visibilité & diffusion',
                'champs' => ['visibilite'],
                'proprietes' => array_keys(LieuFormCatalog::visibility()),
                'blocs' => ['sites', 'salesforce'],
            ],
            [
                'titre' => 'Suggestions IA & historique',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['suggestions', 'historique'],
            ],
        ];
    }

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>}> */
    private static function restaurant(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'businessPremium', 'partenaireBp', 'telephone', 'siteOfficiel', 'youtubeUrl'],
                'proprietes' => ['label', 'siteOfficiel', 'youtubeUrl'],
                'blocs' => ['suggestions_attente'],
            ],
            [
                'titre' => 'Localisation & accès',
                'champs' => ['localisation', 'acces', 'accesPmr', 'toilettesPmr'],
                'proprietes' => ['localisation', 'acces', 'accesPmr', 'toilettesPmr'],
                'blocs' => [],
            ],
            [
                'titre' => 'Descriptif',
                'champs' => ['descriptionGenerale', 'atouts'],
                'proprietes' => ['descriptionGenerale', 'atouts'],
                'blocs' => [],
            ],
            [
                'titre' => 'Classification',
                'champs' => ['typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement', 'engagementsRse'],
                'proprietes' => ['typesRestaurant', 'typesCuisine', 'specificitesAlimentaires', 'typesEvenement', 'engagementsRse'],
                'blocs' => [],
            ],
            [
                'titre' => 'Services & équipements',
                'champs' => ['services', 'equipements'],
                'proprietes' => ['services', 'equipements'],
                'blocs' => [],
            ],
            [
                'titre' => 'Salles & capacités',
                'champs' => ['salles'],
                'proprietes' => ['salles'],
                'blocs' => [],
            ],
            [
                'titre' => 'Privatisation & disponibilités',
                'champs' => ['privatisationTotale', 'privatisationPartielle', 'heureOuverture', 'heureFermeture', 'joursOuverture', 'periodesFermeture'],
                'proprietes' => ['privatisationTotale', 'privatisationPartielle', 'heureOuverture', 'heureFermeture', 'joursOuverture', 'periodesFermeture'],
                'blocs' => [],
            ],
            [
                'titre' => 'Médias & menus',
                'champs' => ['ressources', 'menus', 'supportsCommerciaux', 'documentTitle', 'documentSource'],
                'proprietes' => ['ressources', 'menus'],
                'blocs' => ['medias'],
            ],
            [
                'titre' => 'Collaborateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
            ],
            [
                'titre' => 'Visibilité & diffusion',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['sites', 'salesforce'],
            ],
            [
                'titre' => 'Suggestions IA & historique',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['suggestions', 'historique'],
            ],
        ];
    }

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>}> */
    private static function activite(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'businessPremium', 'partenaireBp', 'telephone', 'prestataire', 'types', 'langues', 'youtubeUrl'],
                'proprietes' => ['label', 'prestataire', 'types', 'langues', 'youtubeUrl'],
                'blocs' => ['suggestions_attente'],
            ],
            [
                'titre' => 'Localisation & zone d\'intervention',
                'champs' => ['modeIntervention', 'localisation', 'touteFrance', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'proprietes' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'blocs' => [],
            ],
            [
                'titre' => 'Descriptif',
                'champs' => ['descriptionGenerale', 'comprendPrestation', 'objectifs', 'plus'],
                'proprietes' => ['descriptionGenerale', 'comprendPrestation', 'objectifs', 'plus'],
                'blocs' => [],
            ],
            [
                'titre' => 'Classification',
                'champs' => ['thematiques', ...array_values(ActiviteLovCatalog::SOUS_THEMATIQUE_FIELDS), 'engagementsRse'],
                'proprietes' => ['thematiques', 'sousThematiques', 'engagementsRse'],
                'blocs' => [],
            ],
            [
                'titre' => 'Capacités & durées',
                'champs' => ['participantsMin', 'participantsMax', 'dureeMinMinutes', 'dureeMaxMinutes'],
                'proprietes' => ['participantsMin', 'participantsMax', 'dureeMinMinutes', 'dureeMaxMinutes'],
                'blocs' => [],
            ],
            [
                'titre' => 'Tarifs & offres',
                'champs' => ['tarifParPersonne', 'offres'],
                'proprietes' => ['tarifParPersonne', 'offres'],
                'blocs' => [],
            ],
            [
                'titre' => 'Médias',
                'champs' => ['ressources', 'supportsCommerciaux', 'supportTitle', 'supportSource'],
                'proprietes' => ['ressources'],
                'blocs' => ['medias'],
            ],
            [
                'titre' => 'Collaborateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
            ],
            [
                'titre' => 'Visibilité & diffusion',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['sites', 'salesforce'],
            ],
            [
                'titre' => 'Suggestions IA & historique',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['suggestions', 'historique'],
            ],
        ];
    }

    /** @return list<array{titre: string, champs: list<string>, proprietes: list<string>, blocs: list<string>}> */
    private static function service(): array
    {
        return [
            [
                'titre' => 'Informations générales',
                'champs' => ['label', 'businessPremium', 'partenaireBp', 'telephone', 'prestations', ...array_values(ServiceLovCatalog::SOUS_PRESTATION_FIELDS), 'prestataireEsat', 'demarcheRse', 'youtubeUrl'],
                'proprietes' => ['label', 'prestations', 'sousPrestations', 'prestataireEsat', 'demarcheRse', 'youtubeUrl'],
                'blocs' => ['suggestions_attente'],
            ],
            [
                'titre' => 'Prestation & accessibilité',
                'champs' => ['participantsMin', 'participantsMax', 'dureeMinutes', 'adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'materielInclus', 'equipementParticipantsRequis', 'equipementReceptionRequis', 'contraintesLogistiques'],
                'proprietes' => ['participantsMin', 'participantsMax', 'dureeMinutes', 'adapteFemmesEnceintes', 'adapteMalentendants', 'adapteMalvoyants', 'materielInclus', 'equipementParticipantsRequis', 'equipementReceptionRequis', 'contraintesLogistiques'],
                'blocs' => [],
            ],
            [
                'titre' => 'Localisation & zone d\'intervention',
                'champs' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'proprietes' => ['modeIntervention', 'localisation', 'paysMobiles', 'regionsMobiles', 'departementsMobiles'],
                'blocs' => [],
            ],
            [
                'titre' => 'Descriptif',
                'champs' => ['descriptionGenerale'],
                'proprietes' => ['descriptionGenerale'],
                'blocs' => [],
            ],
            [
                'titre' => 'Tarifs',
                'champs' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'],
                'proprietes' => ['tarifParPrestation', 'tarifParPersonne', 'tarifParJour', 'tarifParDemiJournee', 'tarifParHeure', 'surDevis'],
                'blocs' => [],
            ],
            [
                'titre' => 'Médias',
                'champs' => ['ressources', 'supportsCommerciaux', 'supportTitle', 'supportSource'],
                'proprietes' => ['ressources'],
                'blocs' => ['medias'],
            ],
            [
                'titre' => 'Collaborateurs',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['collaborateurs'],
            ],
            [
                'titre' => 'Visibilité & diffusion',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['sites', 'salesforce'],
            ],
            [
                'titre' => 'Suggestions IA & historique',
                'champs' => [],
                'proprietes' => [],
                'blocs' => ['suggestions', 'historique'],
            ],
        ];
    }
}
