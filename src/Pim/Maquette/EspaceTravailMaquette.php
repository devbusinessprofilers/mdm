<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de démonstration de l'écran « Mon espace de travail ».
 *
 * Ces données sont celles de la maquette « MDM Business Profilers » et n'ont
 * aucune vocation à rester : elles servent uniquement à faire vivre
 * l'intégration tant que le domaine n'est pas branché. À supprimer le jour où
 * un service métier alimente l'écran.
 *
 * @phpstan-type Compteur array{libelle: string, valeur: string, note: string, couleur: string}
 * @phpstan-type Ligne array{fiche: string, typologie: string, typologieCouleur: string, motif: string, echeance: string, echeanceTon: string, action: string}
 * @phpstan-type Evenement array{qui: string, quoi: string, quand: string}
 * @phpstan-type Vue array{salutation: string, sousTitre: string, compteurs: list<Compteur>, titre: string, tri: string, lignes: list<Ligne>, raccourcis: list<string>, activite: list<Evenement>}
 */
final class EspaceTravailMaquette
{
    public const ROLE_PAR_DEFAUT = 'supply';

    /** @var array<string, string> */
    public const ROLES = [
        'supply' => 'Supply',
        'admin' => 'Administrateur',
        'cp' => 'Chef de projet',
    ];

    /**
     * Familles de fiches du référentiel et token de couleur associé.
     *
     * @var list<array{string, string}>
     */
    private const TYPOLOGIES = [
        ['Lieux', 'primary-turquoise'],
        ['Restaurants', 'primary-marine'],
        ['Activités', 'secondary-p-che'],
        ['Services évén.', 'secondary-premium'],
        ['Plateaux repas', 'secondary-vert'],
    ];

    /**
     * @return Vue
     */
    public static function pourRole(string $role): array
    {
        return match ($role) {
            'admin' => self::administrateur(),
            'cp' => self::chefDeProjet(),
            default => self::supply(),
        };
    }

    /**
     * @return Vue
     */
    private static function supply(): array
    {
        return [
            'salutation' => 'Bonjour Clémence',
            'sousTitre' => 'Jeudi 12 février · 38 fiches vous sont assignées, 6 arrivent à échéance cette semaine',
            'compteurs' => [
                self::compteur('Mes fiches assignées', 38, '12 sous 50 % de complétude', 'primary-turquoise'),
                self::compteur('En attente de validation', 14, 'dont 3 depuis plus de 5 jours', 'secondary-premium'),
                self::compteur('Contributions prestataires', 62, 'à arbitrer avant publication', 'secondary-p-che'),
                self::compteur('Campagnes IA en cours', 3, '1 204 champs proposés', 'primary-marine'),
            ],
            'titre' => 'À traiter en priorité',
            'tri' => 'Trié par échéance puis par complétude',
            'lignes' => [
                self::ligne('Château de Montvillargenne', 0, 'Complétude 64 % · 4 suggestions IA en attente', "Aujourd'hui", 'Enrichir'),
                self::ligne("Les Jardins d'Épicure", 2, 'Aucune photographie publiée', "Aujourd'hui", 'Compléter'),
                self::ligne('Villa Belrose', 0, 'SIRET absent — publication bloquée', 'Demain', 'Corriger'),
                self::ligne('Le Cloître des Cordeliers', 3, 'Contribution prestataire à arbitrer', 'Demain', 'Arbitrer'),
                self::ligne('Abbaye de Talloires', 1, 'Description EN manquante', '16 février', 'Traduire'),
                self::ligne('Domaine de Vaugouard', 0, 'Écart de commission avec Salesforce', '17 février', 'Arbitrer'),
                self::ligne('Manoir de Kerhuel', 4, 'Tarifs saisis sans devise', '19 février', 'Corriger'),
            ],
            'raccourcis' => [
                'Créer une fiche Lieu',
                'Importer un fichier Excel',
                'Lancer une campagne IA',
                'Exporter 210 champs',
                'Mes vues enregistrées',
            ],
            'activite' => [
                self::evenement('Vous', 'Avez publié « Domaine de Chantilly »', 'il y a 12 minutes'),
                self::evenement('M. Rousseau', 'A validé 6 fiches Restaurants', 'il y a 1 heure'),
                self::evenement('Portail prestataire', '9 nouvelles contributions reçues', 'il y a 2 heures'),
                self::evenement('Campagne IA · RSE', '412 champs enrichis, 81 % acceptés', 'hier, 22:04'),
                self::evenement('Import lot 214', '1 120 fiches mises à jour', 'hier, 06:10'),
            ],
        ];
    }

    /**
     * @return Vue
     */
    private static function administrateur(): array
    {
        return [
            'salutation' => 'Bonjour Clémence',
            'sousTitre' => 'Vue administrateur · paramétrage, intégrité et comptes',
            'compteurs' => [
                self::compteur('Anomalies ouvertes', 318, '47 bloquantes sur 5 règles', 'secondary-rouge'),
                self::compteur('Écarts Salesforce', 47, '132 champs divergents', 'secondary-premium'),
                self::compteur('Champs en attente', 9, 'créés, non encore diffusés', 'primary-turquoise'),
                self::compteur('Comptes à revoir', 5, '3 prestataires, 2 internes', 'primary-marine'),
            ],
            'titre' => 'Intégrité du référentiel',
            'tri' => 'Trié par sévérité puis par volume',
            'lignes' => [
                self::ligne('Règle · SIRET absent ou invalide', 0, '47 fiches bloquées à la publication', 'Bloquante', 'Corriger'),
                self::ligne('Règle · Aucune photographie', 0, '62 fiches sans média publiable', 'Bloquante', 'Corriger'),
                self::ligne('Sites thématiques', 4, '5 sites sur 31 en échec de synchronisation', 'Bloquante', 'Relancer'),
                self::ligne('Champ · lieu.groupe', 0, '128 valeurs de liste, 12 jamais utilisées', 'Majeure', 'Nettoyer'),
                self::ligne('Règle · Capacité incohérente', 0, '31 fiches, théâtre supérieur au maximum', 'Majeure', 'Corriger'),
                self::ligne('Compte · agence-nord@bp.fr', 0, 'Sans connexion depuis 94 jours', 'Mineure', 'Désactiver'),
                self::ligne('Taxonomie · Activités', 2, "9 valeurs proposées par l'IA à valider", 'Mineure', 'Réviser'),
            ],
            'raccourcis' => [
                'Créer un champ',
                'Gérer les listes de valeurs',
                'Rôles & droits',
                'Journal des modifications',
                'Relancer un cycle',
            ],
            'activite' => [
                self::evenement('Vous', 'Avez ajouté le champ « rse.certification »', 'il y a 40 minutes'),
                self::evenement('Système', 'Cycle Salesforce terminé, 47 écarts', 'il y a 12 minutes'),
                self::evenement('Sites thématiques', '5 sites en échec depuis 3 heures', 'il y a 3 heures'),
                self::evenement('M. Rousseau', "A modifié les droits de l'équipe Supply", 'hier, 17:20'),
                self::evenement('Système', 'Sauvegarde du référentiel effectuée', 'hier, 03:12'),
            ],
        ];
    }

    /**
     * @return Vue
     */
    private static function chefDeProjet(): array
    {
        return [
            'salutation' => 'Bonjour Clémence',
            'sousTitre' => 'Vue chef de projet · consultation. Les modifications se font depuis Salesforce.',
            'compteurs' => [
                self::compteur('Fiches consultées', 24, 'sur les 7 derniers jours', 'primary-turquoise'),
                self::compteur('Mes favoris', 12, 'lieux et traiteurs récurrents', 'primary-marine'),
                self::compteur('Demandes en cours', 6, 'événements à sourcer', 'secondary-premium'),
                self::compteur('Fiches signalées', 2, 'informations à corriger', 'secondary-p-che'),
            ],
            'titre' => 'Vos fiches récentes',
            'tri' => "Lecture seule · signaler une erreur envoie une demande à l'équipe Supply",
            'lignes' => [
                self::ligne('Château de Montvillargenne', 0, 'Consultée pour « Séminaire Groupe Alpha »', 'il y a 2 h', 'Ouvrir'),
                self::ligne('Le Grand Hôtel des Bains', 0, 'Ajoutée aux favoris', 'hier', 'Ouvrir'),
                self::ligne('Les Voiles Blanches', 1, 'Signalée · téléphone erroné', 'hier', 'Suivre'),
                self::ligne('Domaine de Fontenille', 0, 'Consultée pour « Convention Février »', '10 février', 'Ouvrir'),
                self::ligne('Le Cellier des Ducs', 4, 'Consultée pour « Cocktail lancement »', '9 février', 'Ouvrir'),
                self::ligne('Abbaye de Talloires', 0, 'Signalée · capacité obsolète', '6 février', 'Suivre'),
                self::ligne('Villa Océane', 2, 'Ajoutée aux favoris', '4 février', 'Ouvrir'),
            ],
            'raccourcis' => [
                'Rechercher un lieu',
                'Mes favoris',
                'Signaler une erreur',
                'Ouvrir dans Salesforce',
            ],
            'activite' => [
                self::evenement('Équipe Supply', 'A corrigé le téléphone des « Voiles Blanches »', 'il y a 3 heures'),
                self::evenement('Vous', 'Avez consulté 6 fiches Restaurants', 'hier'),
                self::evenement('Équipe Supply', 'A publié 4 nouveaux lieux en Normandie', 'hier'),
                self::evenement('Vous', 'Avez signalé « Abbaye de Talloires »', '6 février'),
                self::evenement('Système', 'Votre export « Lieux Hauts-de-France » est prêt', '5 février'),
            ],
        ];
    }

    /**
     * @return Compteur
     */
    private static function compteur(string $libelle, int $valeur, string $note, string $couleur): array
    {
        return [
            'libelle' => $libelle,
            'valeur' => number_format($valeur, 0, ',', "\u{202F}"),
            'note' => $note,
            'couleur' => $couleur,
        ];
    }

    /**
     * @param int $typologie index dans self::TYPOLOGIES
     *
     * @return Ligne
     */
    private static function ligne(string $fiche, int $typologie, string $motif, string $echeance, string $action): array
    {
        $famille = self::TYPOLOGIES[$typologie] ?? self::TYPOLOGIES[0];

        return [
            'fiche' => $fiche,
            'typologie' => $famille[0],
            'typologieCouleur' => $famille[1],
            'motif' => $motif,
            'echeance' => $echeance,
            'echeanceTon' => self::tonEcheance($echeance),
            'action' => $action,
        ];
    }

    /**
     * @return Evenement
     */
    private static function evenement(string $qui, string $quoi, string $quand): array
    {
        return ['qui' => $qui, 'quoi' => $quoi, 'quand' => $quand];
    }

    /**
     * Reprend la règle de la maquette : une échéance du jour ou une anomalie
     * bloquante passe en rouge, le lendemain ou une anomalie majeure en doré.
     */
    private static function tonEcheance(string $echeance): string
    {
        if (1 === preg_match('/Aujourd|Bloquante/u', $echeance)) {
            return 'urgent';
        }

        if (1 === preg_match('/Demain|Majeure/u', $echeance)) {
            return 'proche';
        }

        return 'neutre';
    }
}
