<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de démonstration des listes de fiches du référentiel.
 *
 * Deux écrans partagent la même table : « Référentiel général » (toutes
 * typologies) et « Lieux » (une seule typologie, avec sélection multiple).
 * Les lignes sont produites par le même algorithme que la maquette
 * « MDM Business Profilers » : mêmes noms, mêmes rotations de ville, statut,
 * complétude, source et canaux.
 *
 * Contenu jetable, à supprimer dès qu'un service métier alimente les écrans.
 *
 * @phpstan-type Typologie array{libelle: string, couleur: string, total: string, completude: int}
 * @phpstan-type Canal array{code: string, actif: bool}
 * @phpstan-type Colonne array{cle: string, libelle: string}
 * @phpstan-type Ligne array{nom: string, reference: string, ville: string, typologie: string, typologieCouleur: string, sousTypologie: string, statut: string, statutFg: string, statutBg: string, completude: int, completudeCouleur: string, completudeAlerte: bool, canaux: list<Canal>, source: string, sourceCouleur: string, modification: string, selectionnee: bool, surlignee: bool}
 * @phpstan-type Filtre array{libelle: string, actif: bool}
 * @phpstan-type Site array{libelle: string, retenu: bool, verrouille: bool, mention: string}
 * @phpstan-type GroupeSites array{nom: string, compte: string, sites: list<Site>}
 * @phpstan-type ChampClasse array{label: string, compte: string, valeurs: list<string>}
 * @phpstan-type PuceSite array{libelle: string, reste: bool}
 * @phpstan-type Page array{libelle: string, courante: bool}
 * @phpstan-type Entete array{titre: string, sousTitre: string, vueEnregistree: string, note: string, recherche: string}
 */
final class ReferentielMaquette
{
    /** @var list<string> Actions proposées quand des fiches sont sélectionnées */
    public const ACTIONS_GROUPEES = [
        'Enrichir par IA',
        'Changer de statut',
        'Assigner',
        'Exporter',
        'Publier',
    ];

    public const LIBELLE_SELECTION = '3 fiches sélectionnées';

    /** @var list<array{string, string, string, int}> libellé, token de couleur, total, complétude */
    private const FAMILLES = [
        ['Lieux', 'primary-turquoise', '12 480', 68],
        ['Restaurants', 'primary-marine', '3 210', 74],
        ['Activités', 'secondary-p-che', '1 884', 51],
        ['Services évén.', 'secondary-premium', '962', 46],
        ['Plateaux repas', 'secondary-vert', '417', 39],
    ];

    /** @var list<list<string>> Sous-typologies, dans l'ordre de self::FAMILLES */
    private const SOUS_TYPOLOGIES = [
        ['Hôtel · 4★', 'Château', 'Domaine', 'Centre de congrès', 'Manoir'],
        ['Gastronomique', 'Bistronomique', 'Brasserie', 'Rooftop', "Table d'hôtes"],
        ['Team building', 'Atelier créatif', 'Sport & aventure', 'Jeu de piste', 'Escape game'],
        ['Audiovisuel', 'Décoration', 'Transport', 'Sécurité', 'Animation'],
        ['Traiteur', 'Plateau individuel', 'Cocktail', 'Buffet', 'Food truck'],
    ];

    /** @var list<string> */
    private const PREFIXES = ['LIE', 'RES', 'ACT', 'SEV', 'PLR'];

    /** @var list<array{string, string, string}> libellé, token de texte, token de fond */
    private const STATUTS = [
        ['Brouillon', 'neutral-500', 'neutral-200'],
        ['En enrichissement', 'secondary-premium', 'secondary-prenium-p-le'],
        ['Validée', 'primary-marine', 'primary-bleu-clair'],
        ['Publiée', 'secondary-vert', 'secondary-vert-p-le'],
        ['Archivée', 'neutral-400', 'neutral-200'],
    ];

    /** @var list<array{string, string}> libellé, token de couleur */
    private const SOURCES = [
        ['MDM', 'primary-turquoise'],
        ['Portail', 'secondary-p-che'],
        ['Import', 'neutral-500'],
        ['Salesforce', 'primary-marine'],
    ];

    /** @var list<int> */
    private const COMPLETUDES = [96, 43, 78, 12, 64, 100, 55, 88, 31, 71];

    /** @var list<string> */
    private const CANAUX = ['MP', 'ST', 'SF', 'PP'];

    /** @var list<string> */
    private const VILLES = [
        'Gouvieux', 'Deauville', 'Chantilly', 'Aix-en-Provence', 'Biarritz', 'Lyon', 'Bordeaux',
        'Annecy', 'Reims', 'Saint-Malo', 'Avignon', 'Nantes', 'Strasbourg', 'Toulouse', 'Cannes',
        'Évian', 'Honfleur', 'Vichy', 'Colmar', 'La Baule', 'Arcachon', 'Beaune', 'Dinard',
        'Étretat', 'Megève',
    ];

    /** @var list<string> */
    private const NOMS = [
        'Château de Montvillargenne', 'Domaine de Chantilly', "Les Jardins d'Épicure", 'Villa Belrose',
        'Le Cloître des Cordeliers', 'Abbaye de Talloires', 'Manoir de Kerhuel', 'Le Grand Hôtel des Bains',
        'Domaine de la Cour', 'Château des Vigiers', 'Le Clos Saint-Martin', 'Hôtel des Quatre Vents',
        'La Ferme du Chêne', 'Domaine de Vaugouard', 'Le Pavillon des Ormes', 'Château de la Rive',
        'Les Terrasses du Lac', 'Le Prieuré Saint-Léger', 'Domaine des Hauts Bois', 'Villa Marguerite',
        'Le Moulin de Verzy', 'Château de Belle-Vue', 'La Bastide du Roy', 'Le Comptoir des Halles',
        'Domaine de la Source', 'Le Belvédère', 'Hôtel du Vieux Port', 'Les Écuries de Bel Air',
        'Le Refuge des Cimes', 'Château Grand Barrail', 'La Maison Bleue', 'Le Cellier des Ducs',
        'Domaine de Rochevilaine', 'Les Voiles Blanches', "Le Verger d'Auteuil", 'Château de Sainte-Croix',
        'La Grange aux Étoiles', "Le Nid d'Aigle", 'Domaine du Petit Bois', 'Villa Océane',
        'Le Clos des Lilas', 'Hôtel de la Plage', 'Les Caves du Roy', 'Le Pressoir',
        'Domaine de Fontenille', "Château de l'Yeuse", 'La Closerie', 'Le Phare de Kermorvan',
        "Les Rives d'Argent", 'Le Jardin Secret',
    ];

    /** @var list<string> */
    /**
     * Adresse postale par ville : rue, code postal, coordonnées GPS. La modale
     * d'édition rapide résout l'adresse depuis la ville de la ligne.
     *
     * @var array<string, array{string, string, string}>
     */
    private const ADRESSES = [
        'Gouvieux' => ['6 avenue François Mathet', '60270', '49,1912 · 2,4204'],
        'Deauville' => ['12 rue Désiré Le Hoc', '14800', '49,3600 · 0,0730'],
        'Chantilly' => ['7 rue du Connétable', '60500', '49,1936 · 2,4699'],
        'Aix-en-Provence' => ['18 cours Mirabeau', '13100', '43,5279 · 5,4497'],
        'Biarritz' => ["3 avenue de l'Impératrice", '64200', '43,4855 · -1,5590'],
        'Lyon' => ['24 quai Saint-Antoine', '69002', '45,7620 · 4,8299'],
        'Bordeaux' => ["9 cours de l'Intendance", '33000', '44,8419 · -0,5762'],
        'Annecy' => ['15 rue Sainte-Claire', '74000', '45,8987 · 6,1264'],
        'Reims' => ["4 place Drouet-d'Erlon", '51100', '49,2555 · 4,0286'],
        'Saint-Malo' => ['8 esplanade Saint-Vincent', '35400', '48,6493 · -2,0257'],
        'Avignon' => ["21 place de l'Horloge", '84000', '43,9494 · 4,8055'],
        'Nantes' => ['6 rue Crébillon', '44000', '47,2135 · -1,5610'],
        'Strasbourg' => ['11 place Kléber', '67000', '48,5832 · 7,7455'],
        'Toulouse' => ["17 rue d'Alsace-Lorraine", '31000', '43,6021 · 1,4444'],
        'Cannes' => ['45 boulevard de la Croisette', '06400', '43,5504 · 7,0174'],
        'Évian' => ['2 rue Nationale', '74500', '46,4008 · 6,5878'],
        'Honfleur' => ['5 quai Sainte-Catherine', '14600', '49,4212 · 0,2327'],
        'Paris' => ['24 boulevard Pasteur', '75015', '48,8402 · 2,3103'],
        'Vichy' => ['9 rue du Parc', '03200', '46,1278 · 3,4258'],
        'Colmar' => ['16 rue des Tanneurs', '68000', '48,0779 · 7,3585'],
        'La Baule' => ['42 avenue du Général de Gaulle', '44500', '47,2861 · 2,3931'],
        'Arcachon' => ['8 boulevard de la Plage', '33120', '44,6588 · 1,1683'],
        'Beaune' => ["5 rue de l'Hôtel-Dieu", '21200', '47,0231 · 4,8383'],
        'Dinard' => ['21 avenue George V', '35800', '48,6320 · 2,0611'],
        'Étretat' => ['3 rue Adolphe Boissaye', '76790', '49,7073 · 0,2039'],
        'Megève' => ['114 route du Palais des Sports', '74120', '45,8567 · 6,6178'],
    ];

    /** @var array<string, string> Libellé de la gamme, affiché en badge */
    public const GAMMES = ['lieu' => 'Lieux', 'restaurant' => 'Restaurants'];

    /**
     * Classification proposée par gamme. La maquette n'ouvre la modale que sur
     * des lieux — aucun nom de la liste ne déclenche la gamme restaurant —
     * mais les deux jeux sont rendus pour que la règle reste vraie.
     *
     * @var array<string, list<array{string, list<string>}>>
     */
    public const CLASSIFICATION = [
        'lieu' => [
            ['Catégorie de lieu', ['Château', 'Domaine', 'Salle de réception']],
            ['Thématiques & Ambiances', ['Historique', 'Nature', 'Élégant', 'Insolite']],
        ],
        'restaurant' => [
            ['Type de restaurant', ['Bistronomique', 'Cuisine française', 'Terrasse']],
        ],
    ];

    /**
     * Sites de diffusion, par groupe. La mention vaut « obligatoire » — le
     * site ne peut alors pas être décoché — ou « payant », ou rien.
     *
     * @var list<array{string, list<array{string, string}>}>
     */
    private const SITES = [
        ['Réseau Business Profilers', [
            ['Business Profilers', 'obligatoire'], ['BP Lieux', ''],
            ['BP Séminaires', ''], ['BP Événements', ''],
        ]],
        ['Partenaires MICE', [
            ['Bedouk', ''], ['ABC Salles', ''], ['1001 Salles', ''], ['Séminaire.com', ''],
            ['Kactus', ''], ['Eventmaker', ''], ['MeetingPackage', ''], ['Cvent', ''],
            ['VenueDirectory', ''],
        ]],
        ['Régies & médias', [
            ['Le Figaro Événements', 'payant'], ['Les Échos Le Parisien', 'payant'],
            ['Challenges', ''], ['Stratégies', ''], ["Voyages d'Affaires", ''],
            ['Déplacements Pros', ''], ["L'Écho Touristique", ''], ['Tendance Hôtellerie', ''],
        ]],
        ['International', [
            ['Trivago Business', ''], ['Booking Meetings', ''], ['HRS Meetings', ''],
            ['Cituation', ''], ['Venuu', ''], ['Tagvenue', ''], ['VenueScanner', ''],
            ['Spacebase', ''], ['EventUp', ''], ['Hire Space', ''],
        ]],
    ];

    /** @var list<string> Sites retenus à l'ouverture */
    private const SITES_RETENUS = [
        'Business Profilers', 'BP Séminaires', 'BP Lieux', 'Bedouk', 'Kactus',
        'Séminaire.com', 'Le Figaro Événements', 'Tagvenue', 'Spacebase',
    ];

    /** @var list<array{string, string}> Suggestions de l'autocomplétion d'adresse */
    public const SUGGESTIONS_ADRESSE = [
        ['6 avenue François Mathet', '60270 Gouvieux · Oise · France'],
        ['6 avenue François Mathet', '60500 Chantilly · Oise · France'],
        ['6 rue François Mathet', '60270 Gouvieux · Oise · France'],
        ['6 avenue Maréchal Foch', '60270 Gouvieux · Oise · France'],
    ];

    /** Requête en cours de frappe, affichée par l'état « adresse ». */
    public const REQUETE_ADRESSE = '6 avenue François Mat';

    /** @var list<array{string, string}> Champ et changement, pour la confirmation */
    public const MODIFICATIONS_EN_COURS = [
        ['Nom', '→ Château de Montvillargenne — Hôtel & Spa'],
        ['Adhérent Business Premium', 'désactivé → activé'],
        ['Sites de diffusion', '+ Kactus, + Spacebase'],
    ];

    /** Nombre de puces de sites affichées dans le résumé avant le « +N ». */
    private const PUCES_SITES = 4;

    private const ANCIENNETES = ['il y a 4 min', 'il y a 2 h', 'hier', '12 fév.', '9 fév.', '3 fév.'];

    /** @var list<string> */
    private const AUTEURS = ['C. Philips', 'M. Rousseau', 'Import', 'Portail'];

    /**
     * @return Entete
     */
    public static function entete(bool $general): array
    {
        return $general
            ? [
                'titre' => 'Référentiel général',
                'sousTitre' => '18 953 fiches, toutes typologies confondues · 3 041 à compléter',
                'vueEnregistree' => 'Toutes les fiches',
                'note' => '40 fiches affichées sur 18 953 · lignes de 44 px',
                'recherche' => 'Nom, SIRET, ville, identifiant…',
            ]
            : [
                'titre' => 'Lieux',
                'sousTitre' => '12 480 fiches · 1 240 à compléter',
                'vueEnregistree' => 'Lieux à enrichir · FR',
                'note' => '50 fiches affichées sur 12 480 · lignes de 44 px',
                'recherche' => 'Nom, SIRET, ville, identifiant…',
            ];
    }

    /**
     * Colonnes de la table, dans l'ordre.
     *
     * Le référentiel général affiche la typologie (la famille), l'écran Lieux
     * affiche la sous-typologie et la place après la ville.
     *
     * @return list<Colonne>
     */
    public static function colonnes(bool $general): array
    {
        $colonnes = [
            ['cle' => 'check', 'libelle' => ''],
            ['cle' => 'nom', 'libelle' => $general ? 'Fiche' : 'Nom du lieu'],
        ];

        if ($general) {
            $colonnes[] = ['cle' => 'typologie', 'libelle' => 'Typologie'];
        }

        $colonnes[] = ['cle' => 'ville', 'libelle' => 'Ville'];

        if (!$general) {
            $colonnes[] = ['cle' => 'sousTypologie', 'libelle' => 'Typologie'];
        }

        $colonnes[] = ['cle' => 'statut', 'libelle' => 'Statut'];
        $colonnes[] = ['cle' => 'completude', 'libelle' => 'Complétude'];
        $colonnes[] = ['cle' => 'source', 'libelle' => 'Source'];
        $colonnes[] = ['cle' => 'maj', 'libelle' => 'Dernière modification'];
        // Colonne du crayon d'édition rapide : sans intitulé dans la maquette.
        $colonnes[] = ['cle' => 'actions', 'libelle' => ''];

        return $colonnes;
    }

    /**
     * Répartition par typologie, affichée en tête du référentiel général.
     *
     * La jauge de chaque carte reprend la couleur de sa typologie — celle de
     * la pastille — et non un code couleur par seuil : sur cet écran la barre
     * identifie la famille, elle ne juge pas son niveau de complétude.
     *
     * @return list<Typologie>
     */
    public static function typologies(): array
    {
        $cartes = [];

        foreach (self::FAMILLES as [$libelle, $couleur, $total, $completude]) {
            $cartes[] = [
                'libelle' => $libelle,
                'couleur' => $couleur,
                'total' => $total,
                'completude' => $completude,
            ];
        }

        return $cartes;
    }

    /**
     * @return list<Filtre>
     */
    public static function filtres(bool $general): array
    {
        $libelles = $general
            ? ['Typologie · 5', 'Statut', 'Complétude · < 50 %', 'Région', 'Canal', 'Autorité']
            : ['Statut · 2', 'Complétude · < 50 %', 'Région', 'Canal de diffusion', 'Autorité', 'Assigné à'];

        $filtres = [];

        foreach ($libelles as $libelle) {
            // La maquette considère qu'un filtre portant une valeur — donc un
            // « · » — est un filtre actif.
            $filtres[] = ['libelle' => $libelle, 'actif' => str_contains($libelle, '·')];
        }

        return $filtres;
    }

    /**
     * Le référentiel général affiche 40 lignes toutes typologies confondues ;
     * l'écran Lieux en affiche 50, toutes de la famille « Lieux ».
     *
     * @return list<Ligne>
     */
    public static function lignes(bool $general): array
    {
        $total = $general ? 40 : 50;
        $lignes = [];

        for ($i = 0; $i < $total; ++$i) {
            $rang = $general ? $i % \count(self::FAMILLES) : 0;
            $famille = self::FAMILLES[$rang];
            $sousTypologies = self::SOUS_TYPOLOGIES[$rang];
            $statut = self::STATUTS[($i * 3 + 1) % \count(self::STATUTS)];
            $source = self::SOURCES[$i % \count(self::SOURCES)];
            $completude = self::COMPLETUDES[$i % \count(self::COMPLETUDES)];
            $selectionnee = $i < 3;

            $nom = self::NOMS[$i];
            $ville = self::VILLES[$i % \count(self::VILLES)];
            [$rue, $codePostal, $gps] = self::ADRESSES[$ville];

            $lignes[] = [
                'nom' => $nom,
                'reference' => self::PREFIXES[$rang]
                    . '-' . str_pad((string) (4821 + $i * 7), 6, '0', \STR_PAD_LEFT),
                'ville' => $ville,
                // Reprises par la modale d'édition rapide.
                'gamme' => 1 === preg_match('/Restaurant|Bistrot/', $nom) ? 'restaurant' : 'lieu',
                'rue' => $rue,
                'codePostal' => $codePostal,
                'gps' => $gps,
                'statutPubliee' => 'Publiée' === $statut[0],
                'typologie' => $famille[0],
                'typologieCouleur' => $famille[1],
                'sousTypologie' => $sousTypologies[$i % \count($sousTypologies)],
                'statut' => $statut[0],
                'statutFg' => $statut[1],
                'statutBg' => $statut[2],
                'completude' => $completude,
                'completudeCouleur' => match (true) {
                    $completude >= 80 => 'secondary-vert',
                    $completude >= 50 => 'primary-turquoise',
                    $completude >= 25 => 'secondary-premium',
                    default => 'secondary-p-che',
                },
                'completudeAlerte' => $completude < 25,
                'canaux' => self::canaux($i),
                'source' => $source[0],
                'sourceCouleur' => $source[1],
                'modification' => self::ANCIENNETES[$i % \count(self::ANCIENNETES)]
                    . ' · ' . self::AUTEURS[$i % \count(self::AUTEURS)],
                'selectionnee' => $selectionnee,
                // Seul l'écran Lieux teinte la ligne sélectionnée ; le
                // référentiel général coche la case sans surligner.
                'surlignee' => $selectionnee && !$general,
            ];
        }

        return $lignes;
    }

    /**
     * @return list<Page>
     */
    public static function pagination(bool $general): array
    {
        $pages = [];

        foreach (['‹', '1', '2', '3', '…', $general ? '474' : '250', '›'] as $index => $libelle) {
            $pages[] = ['libelle' => $libelle, 'courante' => 1 === $index];
        }

        return $pages;
    }

    /**
     * Sites de diffusion groupés, chacun avec son décompte de sites retenus.
     *
     * @return list<GroupeSites>
     */
    public static function sitesGroupes(): array
    {
        $groupes = [];

        foreach (self::SITES as [$nom, $sites]) {
            $lignes = [];
            $retenus = 0;

            foreach ($sites as [$libelle, $mention]) {
                $retenu = \in_array($libelle, self::SITES_RETENUS, true);
                $retenus += $retenu ? 1 : 0;

                $lignes[] = [
                    'libelle' => $libelle,
                    'retenu' => $retenu,
                    'verrouille' => 'obligatoire' === $mention,
                    'mention' => $mention,
                ];
            }

            $groupes[] = [
                'nom' => $nom,
                'compte' => $retenus . '/' . \count($sites),
                'sites' => $lignes,
            ];
        }

        return $groupes;
    }

    /** Compte affiché en tête du champ : « 9 sur 31 ». */
    public static function sitesCompte(): string
    {
        $total = 0;

        foreach (self::SITES as [, $sites]) {
            $total += \count($sites);
        }

        return \count(self::SITES_RETENUS) . ' sur ' . $total;
    }

    /**
     * Puces du résumé : les quatre premiers sites retenus, puis « +N » pour le
     * reste. La dernière puce porte un fond neutre, d'où le drapeau `reste`.
     *
     * @return list<PuceSite>
     */
    public static function puceSites(): array
    {
        $puces = [];

        foreach (\array_slice(self::SITES_RETENUS, 0, self::PUCES_SITES) as $libelle) {
            $puces[] = ['libelle' => $libelle, 'reste' => false];
        }

        // Neuf sites retenus pour quatre puces : la maquette condense toujours.
        $puces[] = [
            'libelle' => '+' . (\count(self::SITES_RETENUS) - self::PUCES_SITES),
            'reste' => true,
        ];

        return $puces;
    }

    /**
     * Décompte par groupe, affiché sous le champ : « Partenaires MICE 2/9 ».
     *
     * @return list<string>
     */
    public static function sitesDecompte(): array
    {
        $lignes = [];

        foreach (self::sitesGroupes() as $groupe) {
            $lignes[] = $groupe['nom'] . ' ' . $groupe['compte'];
        }

        return $lignes;
    }

    /**
     * Champs de classification de la gamme, avec leurs valeurs en puces.
     *
     * @return list<ChampClasse>
     */
    public static function classification(string $gamme): array
    {
        $champs = [];

        foreach (self::CLASSIFICATION[$gamme] ?? [] as [$label, $valeurs]) {
            $champs[] = [
                'label' => $label,
                'compte' => \count($valeurs) . ' sélectionnés',
                'valeurs' => $valeurs,
            ];
        }

        return $champs;
    }

    /**
     * Canaux de diffusion d'une fiche : la maquette en active deux sur trois,
     * par rotation sur l'index de ligne.
     *
     * @return list<Canal>
     */
    private static function canaux(int $ligne): array
    {
        $canaux = [];

        foreach (self::CANAUX as $rang => $code) {
            $canaux[] = ['code' => $code, 'actif' => 0 !== ($ligne + $rang) % 3];
        }

        return $canaux;
    }
}
