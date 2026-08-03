<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Vue de l'éditeur de fiche Lieu, assemblée à partir de {@see FicheLieuDonnees}.
 *
 * Reprend les règles de la maquette « MDM Business Profilers » :
 * quelles sections portent des puces, une grille de médias, les formules de
 * visibilité, les disponibilités ou la matrice de capacités ; et quelles
 * suggestions l'IA propose pour les champs vides.
 *
 * Contenu jetable, à supprimer dès qu'un service métier alimente l'écran.
 *
 * @phpstan-import-type Section from FicheLieuDonnees
 * @phpstan-import-type Champ from FicheLieuDonnees
 * @phpstan-import-type GroupePuces from FicheLieuDonnees
 *
 * @phpstan-type Onglet array{index: int, nom: string, icone: string, completude: int, actif: bool}
 * @phpstan-type Suggestion array{champ: string, valeur: string, confiance: string}
 * @phpstan-type Jour array{nom: string, ouvert: bool}
 * @phpstan-type Periode array{nom: string, dates: string}
 * @phpstan-type Salle array{nom: string, chiffres: list<int>, equipements: list<bool>, photo: bool, enAvant: bool}
 * @phpstan-type Option array{libelle: string, selectionnee: bool}
 * @phpstan-type Canal array{libelle: string, fr: int, en: int}
 * @phpstan-type Evenement array{qui: string, quand: string, source: string, changement: string}
 * @phpstan-type Collaborateur array{nom: string, prenom: string, email: string, telephone: string,
 *     role: string, principal: bool, demandes: bool, contenus: bool, paiements: bool, supprimable: bool}
 */
final class FicheLieuMaquette
{
    /**
     * Sections portées par le groupe « Ma fiche » du rail ; le reste va dans
     * « Paramètres ». La maquette a fait passer « Booster ma visibilité » de
     * Paramètres à Ma fiche : la coupure est à 13, plus à 12.
     */
    /**
     * Correspondance entre les noms de glyphes du handoff et ceux du jeu
     * d'icônes du portail prestataire. Elle vit ici, pas dans les données :
     * `FicheLieuDonnees` est généré depuis le handoff et ne doit pas être
     * retouché à la main.
     *
     * @var array<string, string>
     */
    private const ICONES_PORTAIL = [
        'info' => 'info-circle',
        'mappin' => 'pin',
        'userrect' => 'user-rectangle',
        'fork' => 'utensils',
        'bike' => 'biking',
        'euro' => 'currency-euro',
        'okcircle' => 'check-circle',
    ];

    public const SECTIONS_MA_FICHE = 13;

    public const TITRE = 'Jeanne & The Forest - Château de Montvillargenne';
    public const REFERENCE = 'LIE-004821 · v14';
    public const COMPLETUDE = 64;
    public const COMPLETUDE_DETAIL = '134 / 210 champs';

    /** Sections qui affichent la carte « Disponibilités » (indices de la maquette). */
    private const AVEC_DISPONIBILITES = [0, 6];

    /** Section qui affiche la matrice de capacités. */
    private const AVEC_CAPACITES = 5;

    private const SECTION_MEDIAS = 'Médias';
    private const SECTION_COLLABORATEURS = 'Collaborateurs';
    private const SECTION_BOOSTER = 'Booster ma visibilité';

    /** @var list<string> Jours ouvrés dans la maquette */
    private const JOURS_OUVERTS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

    /** @var list<string> */
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    /**
     * Intitulé de colonne et icône du portail.
     *
     * Les glyphes du handoff (Metre1, RUnionU, TablesRondes…) sont en commentaire :
     * le jeu d'icônes du portail couvre déjà les treize configurations, on prend
     * les siennes plutôt que de transcrire les tracés de la maquette.
     *
     * @var list<array{string, string}>
     */
    public const COLONNES_CAPACITES = [
        ['m³', 'cube'],                 // Metre1
        ['Théâtre', 'conference'],      // RUnionConfRence
        ['Réunion', 'meeting'],         // RUnionRectangle
        ['En U', 'u-meeting'],          // RUnionU
        ['Classe', 'classroom'],        // RUnionClassroom
        ['Cabaret', 'cabaret'],         // TablesRondes
        ['Banquet', 'banquet'],         // RUnionCercle
        ['Cocktail', 'cocktail'],       // Cocktail
        ['Lumière naturelle', 'sun'],   // Sunlight
        ['Climatisé', 'wind'],          // Wind
        ['PMR', 'wheel-chair'],         // Pmr
        ['Espace dansant', 'dance'],    // Dance
        ['Plan', 'file-pdf'],           // FilePdf2
    ];

    /**
     * Salles de la matrice de capacités.
     * Nom, surface en m², capacités par configuration (7), équipements (4),
     * présence d'une photo, ligne mise en avant.
     *
     * @var list<array{string, int, list<int>, list<bool>, bool, bool}>
     */
    private const SALLES = [
        ['Pleyel 1', 249, [120, 96, 40, 64, 60, 80, 120], [false, true, false, true], true, true],
        ['Pleyel 2', 132, [64, 48, 28, 36, 40, 48, 70], [true, true, true, true], true, false],
        ['Salle de bal', 186, [90, 72, 34, 48, 50, 64, 95], [true, true, false, true], true, false],
        ['Orangerie', 74, [36, 30, 18, 24, 24, 32, 45], [true, false, true, false], false, false],
        ['Bibliothèque', 28, [14, 12, 8, 10, 10, 12, 16], [false, false, false, false], false, false],
    ];

    public const PLAN_SALLE = 'planv2.pdf';

    /** Nombre de sections. */
    public static function total(): int
    {
        return \count(FicheLieuDonnees::SECTIONS);
    }

    /** Ramène un indice de section dans les bornes. */
    public static function indexValide(int $index): int
    {
        return $index >= 0 && $index < self::total() ? $index : 0;
    }

    /**
     * Les 16 onglets, pour le rail latéral.
     *
     * @return list<Onglet>
     */
    public static function onglets(int $actif): array
    {
        $onglets = [];

        foreach (FicheLieuDonnees::SECTIONS as $index => $section) {
            $onglets[] = [
                'index' => $index,
                'nom' => $section['nom'],
                'icone' => self::ICONES_PORTAIL[$section['icone']] ?? $section['icone'],
                'completude' => $section['completude'],
                'actif' => $index === $actif,
            ];
        }

        return $onglets;
    }

    /**
     * @return Section
     */
    public static function section(int $index): array
    {
        return FicheLieuDonnees::SECTIONS[self::indexValide($index)];
    }

    /**
     * Groupes de puces de la section, s'il y en a.
     *
     * @return list<GroupePuces>
     */
    public static function groupesPuces(int $index): array
    {
        return FicheLieuDonnees::GROUPES_PUCES[self::section($index)['nom']] ?? [];
    }

    public static function aDesFormules(int $index): bool
    {
        return self::SECTION_BOOSTER === self::section($index)['nom'];
    }

    public static function aDesMedias(int $index): bool
    {
        return self::SECTION_MEDIAS === self::section($index)['nom'];
    }

    /**
     * La section « Collaborateurs » ne montre pas la grille de champs : elle a
     * son propre tableau et un panneau latéral.
     */
    public static function aDesCollaborateurs(int $index): bool
    {
        return self::SECTION_COLLABORATEURS === self::section($index)['nom'];
    }

    public static function aDesDisponibilites(int $index): bool
    {
        return \in_array(self::indexValide($index), self::AVEC_DISPONIBILITES, true);
    }

    public static function aDesCapacites(int $index): bool
    {
        return self::AVEC_CAPACITES === self::indexValide($index);
    }

    /** Seule « Informations générales » propose le choix « lieu privatisable ». */
    public static function aPrivatisation(int $index): bool
    {
        return 0 === self::indexValide($index);
    }

    /**
     * Suggestions de l'IA.
     *
     * Règle de la maquette : uniquement les champs vides, modifiables — donc
     * jamais d'autorité Salesforce — et pour lesquels une valeur est réellement
     * proposée. Afficher un score de confiance sur du remplissage serait faux.
     *
     * @return list<Suggestion>
     */
    public static function suggestions(int $index): array
    {
        $confiances = [94, 88, 79, 71];
        $suggestions = [];

        foreach (self::section($index)['champs'] as $champ) {
            if (\count($suggestions) >= 4) {
                break;
            }

            $valeurProposee = FicheLieuDonnees::VALEURS_SUGGEREES[$champ['label']] ?? null;
            // La maquette considère « - » comme une valeur absente ici, alors
            // que le champ, lui, l'affiche tel quel.
            $aRemplir = $champ['vide'] || '-' === $champ['valeur'];

            if (!$aRemplir || $champ['verrouille'] || null === $valeurProposee) {
                continue;
            }

            $rang = \count($suggestions);
            $suggestions[] = [
                'champ' => rtrim($champ['label'], '*'),
                'valeur' => $valeurProposee,
                'confiance' => 1 === preg_match('/EN$|\(EN\)/u', $champ['label'])
                    ? 'traduction'
                    : 'confiance ' . $confiances[$rang] . ' %',
            ];
        }

        return $suggestions;
    }

    /**
     * Points d'intérêt du menu déroulant de type « poi ».
     * Liste et états de sélection repris tels quels de la molécule.
     *
     * @var list<array{string, bool}>
     */
    private const POINTS_INTERET = [
        ['Aéroport Charles de Gaulle', true],
        ['Aéroport Paris-Beauvais Tillé (40 km)', false],
        ['Aéroport Inter. Amiens Henry Potez - Albert Méaulte (40 km)', false],
        ['Aérodrome de Persan-Beaumont (20 km)', false],
    ];

    /** @var list<string> Libellés servis par le menu « poi » */
    private const CHAMPS_POI = ['Aéroport(s)', "Points d'intérêt à moins de 15 min à pied"];

    /**
     * Catalogue d'options par champ de type liste.
     *
     * ATTENTION — contenu INVENTÉ, à la demande, pour que les menus soient
     * démontrables. Le handoff ne fournit de liste réelle que pour les points
     * d'intérêt. Ces valeurs sont plausibles mais n'ont aucune autorité : elles
     * disparaîtront au branchement du service de taxonomies.
     *
     * @var array<string, list<string>>
     */
    private const CATALOGUE = [
        'Typologie*' => [
            'Hôtel - Hôtel 3*', 'Hôtel - Hôtel 4*', 'Hôtel - Hôtel 5*',
            'Château', 'Domaine', 'Centre de congrès', 'Manoir',
        ],
        'Groupe et chaîne hôtelière' => [
            'Indépendant', 'Accor', 'Marriott International', 'Hilton',
            'Relais & Châteaux', 'Tiara Hotels & Resorts', 'Small Luxury Hotels',
        ],
        'Pays*' => [
            'France', 'Belgique', 'Suisse', 'Luxembourg',
            'Espagne', 'Italie', 'Portugal', 'Royaume-Uni',
        ],
        'Arrondissement*' => ['Beauvais', 'Clermont', 'Compiègne', 'Senlis'],
        'Département*' => [
            '02 - Aisne', '59 - Nord', '60 - Oise', '62 - Pas-de-Calais', '80 - Somme',
        ],
        'Région*' => [
            'Auvergne-Rhône-Alpes', 'Bourgogne-Franche-Comté', 'Bretagne',
            'Centre-Val de Loire', 'Corse', 'Grand Est', 'Hauts-de-France',
            'Île-de-France', 'Normandie', 'Nouvelle-Aquitaine', 'Occitanie',
            'Pays de la Loire', "Provence-Alpes-Côte d'Azur",
        ],
        'Style architectural' => [
            'Château XIXᵉ', 'Manoir normand', 'Hôtel particulier',
            'Corps de ferme rénové', 'Bâtisse contemporaine', 'Industriel réhabilité',
        ],
        'Public cible' => [
            'Comités de direction', 'Équipes commerciales', 'Grands groupes',
            'PME & ETI', 'Associations', 'Agences événementielles',
        ],
        'Univers de marque' => [
            'Luxe', 'Premium', 'Lifestyle', 'Affaires', 'Nature & bien-être', 'Familial',
        ],
        'Classement' => [
            'Non classé', '1 étoile', '2 étoiles', '3 étoiles',
            '4 étoiles', '5 étoiles', 'Palace',
        ],
        'Typologie de restaurant*' => [
            'Gastronomique', 'Bistronomique', 'Brasserie', 'Rooftop', "Table d'hôtes",
        ],
        'Type de cuisine*' => [
            'Française', 'Terroir', 'Méditerranéenne', 'Italienne',
            'Asiatique', 'Végétarienne', 'Fusion',
        ],
        "Type d'événement*" => [
            'Petit-déjeuner', 'Brunch', 'Déjeuner assis', 'Cocktail déjeunatoire',
            'Dîner assis', 'Cocktail dînatoire', 'Pause gourmande',
        ],
        "Thématique de l'activité*" => [
            'Team building', 'Incentive', 'Séminaire au vert',
            'Atelier créatif', 'Sport & aventure', 'Culturel',
        ],
        "Environnement de l'activité*" => [
            'Intérieur', 'Extérieur', 'Forêt', "Bord de l'eau", 'Urbain', 'Montagne',
        ],
        'Certification environnementale' => [
            'Aucune', 'Clef Verte', 'Green Globe', 'Écolabel européen',
            'ISO 14001', 'Green Key',
        ],
        'Type de produit à créer*' => [
            'Lieu', 'Restaurant', 'Activité', 'Service événementiel', 'Plateau repas',
        ],
        'Contenu de la formule*' => [
            "Mise en avant page d'accueil", 'Badge « Coup de cœur »', 'Reportage photo',
            'Publication LinkedIn', 'Newsletter partenaires', 'Référencement prioritaire',
        ],
    ];

    /**
     * Options du menu déroulant d'un champ de type liste.
     *
     * Les points d'intérêt viennent de la molécule « poi » du handoff — seule
     * liste réelle qu'il fournit. Les autres proviennent de {@see self::CATALOGUE},
     * qui est du contenu inventé. La sélection est déduite de la valeur du
     * champ, découpée sur les virgules pour les champs multivalués.
     *
     * @return array{type: string, options: list<Option>}
     */
    public static function optionsListe(string $label, string $valeur, bool $vide): array
    {
        if (\in_array($label, self::CHAMPS_POI, true)) {
            $options = [];

            foreach (self::POINTS_INTERET as [$libelle, $selectionnee]) {
                $options[] = ['libelle' => $libelle, 'selectionnee' => $selectionnee];
            }

            return ['type' => 'poi', 'options' => $options];
        }

        $selection = [];

        if (!$vide) {
            foreach (explode(',', $valeur) as $morceau) {
                $morceau = trim($morceau);

                if ('' !== $morceau && '-' !== $morceau) {
                    $selection[] = $morceau;
                }
            }
        }

        // À défaut de catalogue, on retombe sur la valeur du champ.
        $catalogue = self::CATALOGUE[$label] ?? $selection;
        $options = [];

        foreach ($catalogue as $libelle) {
            $options[] = [
                'libelle' => $libelle,
                'selectionnee' => \in_array($libelle, $selection, true),
            ];
        }

        return ['type' => 'checkboxes', 'options' => $options];
    }

    /**
     * @return list<Jour>
     */
    public static function jours(): array
    {
        $jours = [];

        foreach (self::JOURS as $nom) {
            $jours[] = ['nom' => $nom, 'ouvert' => \in_array($nom, self::JOURS_OUVERTS, true)];
        }

        return $jours;
    }

    /**
     * @return list<Periode>
     */
    public static function periodesFermeture(): array
    {
        return array_fill(0, 3, ['nom' => 'Période 1', 'dates' => '12/11/2025 - 20/11/2025']);
    }

    /**
     * Lignes de la matrice de capacités.
     *
     * `chiffres` réunit la surface et les sept configurations : ce sont les
     * huit colonnes numériques de la maquette.
     *
     * @return list<Salle>
     */
    public static function salles(): array
    {
        $salles = [];

        foreach (self::SALLES as [$nom, $surface, $capacites, $equipements, $photo, $enAvant]) {
            $salles[] = [
                'nom' => $nom,
                'chiffres' => array_merge([$surface], $capacites),
                'equipements' => $equipements,
                'photo' => $photo,
                'enAvant' => $enAvant,
            ];
        }

        return $salles;
    }

    /**
     * @return list<Canal>
     */
    public static function canaux(): array
    {
        return [
            ['libelle' => 'Marketplace', 'fr' => 92, 'en' => 41],
            ['libelle' => 'Sites thématiques', 'fr' => 78, 'en' => 0],
            ['libelle' => 'Salesforce', 'fr' => 100, 'en' => 100],
            ['libelle' => 'Portail prestataire', 'fr' => 64, 'en' => 12],
        ];
    }

    /** @var list<string> Colonnes du tableau des collaborateurs */
    public const COLONNES_COLLABORATEURS = [
        'Contact principal', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Rôle',
        'Traite les demandes', 'Traite les contenus', 'Traite les paiements', 'Actions',
    ];

    /** @var list<string> Cases à cocher du panneau latéral */
    public const DROITS_COLLABORATEUR = ['Gérer la fiche', 'Gérer les demandes', 'Gérer les paiements'];

    /**
     * Adresse pré-remplie du formulaire quand il est ouvert depuis l'invitation,
     * et texte grisé du champ email de l'écran d'ajout.
     */
    public const EMAIL_INVITATION = 'yael@businessprofilers.fr';
    public const EMAIL_BROUILLON = 'nom@entreprise.fr';

    /**
     * Nom, prénom, email, téléphone, rôle, puis les quatre pastilles :
     * contact principal, demandes, contenus, paiements.
     *
     * @var list<array{string, string, string, string, string, bool, bool, bool, bool}>
     */
    private const COLLABORATEURS = [
        ['LEROUX', 'Adèle', 'adèle.leroux@yopmail.com', '+33611223344', 'Administrateur', true, true, true, true],
        ['MERCIER', 'Alya', 'alya.mercier@yopmail.com', '+33611223344', 'Manager', false, false, true, true],
        ['MARIE', 'Louis', 'louis.marie@yopmail.com', '+33611223344', 'Utilisateur', false, true, false, true],
    ];

    /**
     * Le contact principal n'est pas supprimable : la maquette masque sa
     * corbeille, il faut d'abord désigner quelqu'un d'autre.
     *
     * @return list<Collaborateur>
     */
    public static function collaborateurs(): array
    {
        $lignes = [];

        foreach (self::COLLABORATEURS as [$nom, $prenom, $email, $tel, $role, $principal, $dem, $con, $pai]) {
            $lignes[] = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $tel,
                'role' => $role,
                'principal' => $principal,
                'demandes' => $dem,
                'contenus' => $con,
                'paiements' => $pai,
                'supprimable' => !$principal,
            ];
        }

        return $lignes;
    }

    /**
     * @return list<Evenement>
     */
    public static function historique(): array
    {
        return [
            [
                'qui' => 'Clémence Philips',
                'quand' => '12 fév. 2026, 14:22',
                'source' => 'MDM',
                'changement' => '« Château de Montvillargenne » → « Jeanne & The Forest — Château de Montvillargenne »',
            ],
            [
                'qui' => 'Import Excel · lot 214',
                'quand' => '3 fév. 2026, 06:10',
                'source' => 'Import',
                'changement' => "Valeur initialisée depuis l'extranet",
            ],
            [
                'qui' => 'Prestataire',
                'quand' => '28 janv. 2026, 09:47',
                'source' => 'Portail',
                'changement' => 'Proposition refusée par M. Rousseau',
            ],
        ];
    }

    /**
     * Seuils de couleur des cellules « complétude par canal ».
     */
    public static function tonCanal(int $valeur): string
    {
        return match (true) {
            $valeur >= 80 => 'vert',
            $valeur >= 40 => 'or',
            default => 'rouge',
        };
    }
}
