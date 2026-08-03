<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Tunnel de création d'une fiche — maquette « Creation fiche ».
 *
 * Sept blocs sur une seule page, pas un assistant pas à pas : le premier
 * choix — la gamme — détermine la structure de la fiche et débloque les six
 * autres. Rien n'est enregistré, c'est une intégration.
 *
 * La maquette expose six états de démonstration ; ils sont servis par la query
 * string, comme les sections de l'éditeur de fiche.
 *
 * @phpstan-type Etat array{id: string, label: string, note: string}
 * @phpstan-type Gamme array{id: string, label: string, sous: string, icone: string, active: bool}
 * @phpstan-type Ancre array{index: int, libelle: string, icone: string, badge: string, ton: string,
 *     verrouillee: bool, active: bool}
 * @phpstan-type Bloc array{index: int, numero: string, titre: string, description: string, type: string,
 *     verrouille: bool, fautif: bool, etiquette: string, etiquetteTon: string}
 * @phpstan-type Puce array{libelle: string, active: bool}
 * @phpstan-type GroupeClasse array{label: string, compte: string, fautif: bool, puces: list<Puce>}
 * @phpstan-type Departement array{numero: string, libelle: string, active: bool}
 * @phpstan-type Interrupteur array{label: string, actif: bool, aide: string, etiquette: string}
 * @phpstan-type Site array{libelle: string, retenu: bool, verrouille: bool, mention: string}
 * @phpstan-type GroupeSites array{nom: string, compte: string, sites: list<Site>}
 * @phpstan-type ChampContact array{label: string, valeur: string, vide: bool, fautif: bool,
 *     indice: string, indiceTon: string}
 * @phpstan-type OptionAcces array{id: string, label: string, aide: string, active: bool, inerte: bool}
 * @phpstan-type Suggestion array{titre: string, detail: string, active: bool}
 * @phpstan-type PartieAdresse array{cle: string, valeur: string}
 * @phpstan-type EtapeCreation array{libelle: string, etat: string}
 */
final class CreationFicheMaquette
{
    /**
     * Les six états de démonstration du handoff.
     *
     * @var list<array{string, string, string}>
     */
    public const ETATS = [
        ['vierge', 'Page vierge', 'Avant sélection de la gamme'],
        ['lieu', 'Gamme Lieux', 'Page complétée'],
        ['activite', 'Gamme Activités', "Zone d'intervention par départements"],
        ['repli', 'Contact de repli activé', 'Envoi des accès désactivé'],
        ['erreurs', 'Erreurs de validation', 'Tentative de création'],
        ['encours', 'Création en cours', 'Corps figé'],
    ];

    /**
     * La gamme engage la structure de la fiche. C'est le seul choix ouvert du
     * cycle de vie, verrouillé après la création.
     *
     * @var list<array{string, string, string, string}>
     */
    private const GAMMES = [
        ['lieu', 'Lieux', 'Château, hôtel, salle', 'bed'],
        ['resto', 'Restaurants', 'Table, brasserie', 'utensils'],
        ['activite', 'Activités', 'Team building, loisirs', 'biking'],
        ['service', 'Prestataires de services', 'Traiteur, audiovisuel', 'users'],
        ['plateau', 'Plateaux repas', 'Catalogue produits', 'note'],
    ];

    /** @var list<string> Gammes qui peuvent n'avoir aucune adresse fixe */
    private const GAMMES_MOBILES = ['activite', 'service'];

    /** @var list<array{string, string, string, string}> titre, description, glyphe, type */
    private const BLOCS = [
        ['Gamme', 'Le seul choix ouvert du cycle de vie : il fixe la structure de la fiche.', 'layout', 'gamme'],
        ['Identité et localisation', 'Ce qui rend la fiche identifiable et situable dans le référentiel.', 'info-circle', 'identite'],
        ['Classification', 'Les axes qui déterminent où la fiche apparaît dans les recherches.', 'confetti', 'classif'],
        ['Statut et référencement', "Visibilité dans le référentiel et niveau d'adhésion.", 'check-circle', 'statut'],
        ['Visibilité', 'Les canaux publics sur lesquels la fiche sera diffusée.', 'eye', 'visibilite'],
        ['Contact prestataire', 'Créé dans le CRM en même temps que la fiche.', 'user-rectangle', 'contact'],
        ['Accès extranet', 'Transmission des identifiants du portail prestataire.', 'lock', 'acces'],
    ];

    /** @var array<string, list<array{string, list<string>}>> */
    private const CLASSIFICATION = [
        'lieu' => [
            ['Catégorie de lieu *', ['Château', 'Domaine', 'Hôtel', 'Salle de réception', 'Lieu atypique', 'Hôtel particulier']],
            ['Thématiques & ambiances', ['Historique', 'Nature', 'Élégant', 'Insolite', "Bord de l'eau", 'Vue panoramique', 'Contemporain']],
        ],
        'resto' => [
            ['Type de restaurant *', ['Bistronomique', 'Gastronomique', 'Brasserie', 'Cuisine du monde', 'Terrasse', 'Privatisable']],
        ],
        'activite' => [
            ["Type d'activité *", ['Team building', 'Sportif', 'Culturel', 'Insolite', 'Bien-être', 'Dégustation']],
        ],
        'service' => [
            ['Type de prestataire *', ['Traiteur', 'Audiovisuel', 'Mobilier', 'Animation', 'Transport', 'Décoration']],
        ],
        'plateau' => [
            ['Type de produit *', ['Plateau repas', 'Buffet', 'Cocktail', 'Petit-déjeuner', 'Pause gourmande']],
        ],
    ];

    /** @var array<string, list<string>> Valeurs cochées d'emblée, par gamme */
    private const CLASSIFICATION_PRE = [
        'lieu' => ['Château', 'Historique', 'Nature', 'Élégant'],
        'resto' => ['Bistronomique', 'Terrasse'],
        'activite' => ['Team building', 'Sportif'],
        'service' => ['Traiteur'],
        'plateau' => ['Plateau repas'],
    ];

    /** @var array<string, string> */
    private const NOMS = [
        'lieu' => 'Château de Montvillargenne',
        'resto' => 'Restaurant Villa M',
        'activite' => 'Stage de pilotage en Formule 3',
        'service' => 'Traiteur Lenôtre Events',
        'plateau' => 'Plateau Signature Lenôtre',
    ];

    /** @var array<string, int> Nombre de champs restant à enrichir après création */
    private const CHAMPS_RESTANTS = ['lieu' => 210, 'resto' => 148];
    private const CHAMPS_RESTANTS_DEFAUT = 96;

    private const ADRESSE_RUE = '6 avenue François Mathet';
    private const ADRESSE_CP = '60270';
    private const ADRESSE_VILLE = 'Gouvieux';
    private const ADRESSE_PAYS = 'France';
    private const ADRESSE_GPS = '49,1912 · 2,4204';

    /** @var list<array{string, string}> */
    private const SUGGESTIONS = [
        ['6 avenue François Mathet', '60270 Gouvieux · Oise · France'],
        ['6 avenue François Mathet', '60500 Chantilly · Oise · France'],
        ['6 rue François Mathet', '60270 Gouvieux · Oise · France'],
        ['6 avenue Maréchal Foch', '60270 Gouvieux · Oise · France'],
    ];

    /** @var list<array{string, string}> */
    private const DEPARTEMENTS = [
        ['75', 'Paris'], ['92', 'Hauts-de-Seine'], ['93', 'Seine-Saint-Denis'], ['94', 'Val-de-Marne'],
        ['78', 'Yvelines'], ['91', 'Essonne'], ['77', 'Seine-et-Marne'], ['95', "Val-d'Oise"],
        ['60', 'Oise'], ['27', 'Eure'], ['45', 'Loiret'], ['51', 'Marne'],
    ];

    /** @var list<string> Départements couverts d'emblée */
    private const DEPARTEMENTS_PRE = ['75', '92', '93', '94', '78', '91', '77', '95'];

    /**
     * Sites de diffusion propres à cet écran. Attention : la liste diffère de
     * celle de la modale d'édition rapide — « Hire Space » n'y est pas. Les
     * deux jeux sont donc tenus séparément.
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
            ['Spacebase', ''], ['EventUp', ''],
        ]],
    ];

    /** @var list<string> */
    private const SITES_PRE = [
        'Business Profilers', 'BP Lieux', 'BP Séminaires', 'Bedouk', 'Kactus',
        'Séminaire.com', 'Le Figaro Événements', 'Tagvenue',
    ];

    /** Nombre de puces de sites affichées avant le « +N ». */
    private const PUCES_SITES = 6;

    /** @var array{prenom: string, nom: string, email: string, tel: string} */
    private const CONTACT = [
        'prenom' => 'Camille', 'nom' => 'Berthier',
        'email' => 'c.berthier@montvillargenne.fr', 'tel' => '+33 3 44 62 37 37',
    ];

    /** @var array{prenom: string, nom: string, email: string, tel: string} */
    private const CONTACT_REPLI = [
        'prenom' => 'Service', 'nom' => 'Référencement',
        'email' => 'referencement@businessprofilers.fr', 'tel' => '+33 1 84 80 60 00',
    ];

    /** @var list<string> */
    private const TRAMES = [
        'Bienvenue — Lieux (FR)', 'Bienvenue — Restaurants (FR)', 'Bienvenue — Activités (FR)',
        'Bienvenue — Prestataires (FR)', 'Welcome — International (EN)',
    ];

    /** @var list<array{string, string}> Étapes du voile de création */
    private const ETAPES_CREATION = [
        ['Fiche créée dans le référentiel', 'faite'],
        ['Contact enregistré dans le CRM', 'encours'],
        ['Envoi des accès extranet', 'attente'],
    ];

    public const REFERENCE = 'Attribuée à la création';

    /** Nom indicatif du champ tant que rien n'est saisi. */
    private const NOM_INDICATIF = 'Nom public de la fiche';

    public static function etatValide(string $etat): string
    {
        foreach (self::ETATS as [$id]) {
            if ($id === $etat) {
                return $etat;
            }
        }

        return 'vierge';
    }

    /**
     * @return Etat
     */
    public static function etat(string $etat): array
    {
        foreach (self::ETATS as [$id, $label, $note]) {
            if ($id === self::etatValide($etat)) {
                return ['id' => $id, 'label' => $label, 'note' => $note];
            }
        }

        return ['id' => 'vierge', 'label' => 'Page vierge', 'note' => 'Avant sélection de la gamme'];
    }

    /**
     * Gamme portée par l'état, éventuellement forcée par la query string.
     * « vierge » et « erreurs » n'en ont aucune ; les autres partent de Lieux,
     * sauf l'état « activite ».
     */
    public static function gammeCourante(string $etat, string $force): string
    {
        if ('' !== $force && \array_key_exists($force, self::CLASSIFICATION)) {
            return $force;
        }

        return match (self::etatValide($etat)) {
            'vierge' => '',
            'activite' => 'activite',
            default => 'lieu',
        };
    }

    /**
     * Tout ce que le gabarit consomme, dérivé comme le fait la maquette.
     *
     * @return array<string, mixed>
     */
    public static function vue(string $etat, string $forceGamme): array
    {
        $etat = self::etatValide($etat);
        $gamme = self::gammeCourante($etat, $forceGamme);

        $vierge = 'vierge' === $etat;
        $occupe = 'encours' === $etat;
        $erreurs = 'erreurs' === $etat;
        $choisie = '' !== $gamme;
        $mobile = \in_array($gamme, self::GAMMES_MOBILES, true);

        /*
         * Une gamme mobile peut troquer son adresse contre une zone
         * d'intervention. Les deux blocs sont alors rendus et c'est le bouton
         * radio qui montre l'un ou l'autre — la maquette permute sans recharger.
         */
        $implantation = 'activite' === $etat ? 'zone' : 'fixe';
        $zoneRendue = $choisie && $mobile;
        $zone = $zoneRendue && 'zone' === $implantation;
        $adresse = $choisie && !$zone;

        $repli = 'repli' === $etat;
        // L'envoi des accès est retenu par défaut ; le contact de repli le
        // rend indisponible, c'est le seul cas où la maquette le retire.
        $envoiActif = !$repli;

        // Les champs obligatoires vides : soit rien n'est choisi, soit la
        // tentative de création vient de les révéler.
        $nomVide = $erreurs || !$choisie;
        $adresseVide = $erreurs || !$choisie;

        $groupes = self::CLASSIFICATION[$gamme] ?? [];
        $preselection = ($erreurs || $vierge) ? [] : (self::CLASSIFICATION_PRE[$gamme] ?? []);

        $manquants = [];

        if ($nomVide) {
            $manquants[] = 'Nom de la fiche';
        }

        if ($adresseVide && $adresse) {
            $manquants[] = 'Adresse postale';
        }

        if ([] !== $groupes && [] === $preselection) {
            $manquants[] = str_replace(' *', '', $groupes[0][0]);
        }

        $contact = $repli ? self::CONTACT_REPLI
            : (($vierge || $erreurs) ? ['prenom' => '', 'nom' => '', 'email' => '', 'tel' => ''] : self::CONTACT);
        $sites = $vierge ? ['Business Profilers'] : self::SITES_PRE;

        return [
            'etat' => self::etat($etat),
            'gamme' => $gamme,
            'choisie' => $choisie,
            'occupe' => $occupe,
            'erreurs' => $erreurs,

            'gammes' => self::gammes($gamme),
            'noteGamme' => $choisie
                ? 'La gamme détermine la structure de la fiche : elle sera verrouillée après la création.'
                : 'Aucune gamme choisie : les blocs suivants restent inactifs tant que la structure '
                    . "n'est pas fixée.",

            'blocs' => self::blocs($choisie, $erreurs, $nomVide, $adresseVide, $preselection, $groupes),
            'ancres' => self::ancres($choisie, $erreurs, $nomVide, $adresseVide, $zone, $preselection,
                $repli, $contact['email'], $sites, $manquants),

            'sousTitre' => $vierge
                ? 'Choisissez une gamme pour afficher les blocs qui en dépendent.'
                : 'Le strict nécessaire pour que la fiche existe. '
                    . "L'enrichissement suit sur la fiche complète.",
            'noteEnrichissement' => $choisie
                ? 'Les ' . (self::CHAMPS_RESTANTS[$gamme] ?? self::CHAMPS_RESTANTS_DEFAUT)
                    . ' champs restants se remplissent sur la fiche, guidés par le score de complétude.'
                : "La création ne recueille que le nécessaire. Le reste s'enrichit ensuite sur la fiche.",
            'etiquetteBrouillon' => $vierge ? '' : ($occupe ? 'Enregistrement…' : 'Brouillon non enregistré'),

            'banniere' => [
                'visible' => $erreurs && [] !== $manquants,
                'titre' => \count($manquants) . ' champ' . (\count($manquants) > 1 ? 's' : '')
                    . ' obligatoire' . (\count($manquants) > 1 ? 's' : '') . ' à compléter',
                'champs' => $manquants,
            ],

            'nom' => [
                'valeur' => $nomVide ? self::NOM_INDICATIF : (self::NOMS[$gamme] ?? ''),
                'vide' => $nomVide,
                'fautif' => $erreurs && $nomVide,
            ],
            'reference' => self::REFERENCE,

            'implantation' => [
                'visible' => $choisie && $mobile,
                'choix' => $implantation,
                /*
                 * Ce qui est réellement affiché, et non le choix brut : une
                 * gamme non mobile garde son adresse même si l'état porte
                 * « zone ». C'est la règle `showAddress = chosen && !showZone`
                 * de la maquette.
                 */
                'affiche' => $zone ? 'zone' : 'fixe',
            ],

            'adresse' => [
                // Rendue dès qu'une gamme est choisie ; masquée par le radio
                // quand c'est la zone qui prend sa place.
                'rendue' => $choisie,
                'visible' => $adresse,
                'vide' => $adresseVide,
                'valeur' => $adresseVide ? 'Saisissez une adresse'
                    : self::ADRESSE_RUE . ', ' . self::ADRESSE_CP . ' ' . self::ADRESSE_VILLE,
                'fautif' => $erreurs && $adresseVide,
                'indice' => $erreurs && $adresseVide
                    ? "L'adresse est obligatoire."
                    : "Autocomplétion : la sélection renseigne l'adresse structurée ci-contre.",
                'parties' => self::partiesAdresse($adresseVide),
                'suggestions' => self::suggestions(),
            ],

            'zone' => [
                'rendue' => $zoneRendue,
                'visible' => $zone,
                'compte' => \count(self::DEPARTEMENTS_PRE) . ' départements couverts',
                'departements' => self::departements(),
            ],

            'classification' => self::classification($groupes, $preselection, $erreurs),
            'interrupteurs' => self::interrupteurs(),

            'sites' => [
                'compte' => \count($sites) . ' sites sur ' . self::totalSites(),
                'puces' => self::pucesSites($sites),
                'groupes' => self::groupesSites($sites),
            ],

            'contact' => [
                'repli' => $repli,
                'aide' => $repli
                    ? "Les quatre champs portent les valeurs génériques de l'agence — ce n'est pas le "
                        . 'contact du prestataire.'
                    : "Renseigne les quatre champs avec les valeurs génériques de l'agence, en lecture seule.",
                'champs' => self::champsContact($contact, $repli, $erreurs),
                'note' => $repli
                    ? 'Aucun contact prestataire ne sera créé : le CRM enregistrera le contact de repli '
                        . "de l'agence."
                    : 'Ce contact sera créé dans le CRM en même temps que la fiche.',
            ],

            'acces' => [
                'options' => self::optionsAcces($repli),
                'trame' => [
                    'actif' => $envoiActif,
                    'valeur' => $envoiActif ? self::TRAMES['activite' === $gamme ? 2 : 0] : 'Aucun envoi',
                    // Le troisième cas de la maquette — « Ne rien envoyer »
                    // choisi à la main — ne s'atteint qu'au clic : il est
                    // porté par le contrôleur `creation`.
                    'indice' => $repli
                        ? 'Envoi désactivé : le contact de repli ne reçoit pas les accès.'
                        : 'Cinq trames disponibles, une par gamme et par langue.',
                ],
            ],

            'barre' => [
                'ton' => $erreurs ? 'erreur' : ($occupe ? 'occupe' : ($choisie ? 'pret' : 'attente')),
                'icone' => $erreurs ? 'warning' : ($occupe ? 'spinner' : ($choisie ? 'check-circle' : 'info-circle')),
                'note' => match (true) {
                    $erreurs => 'Corrigez les champs signalés pour créer la fiche.',
                    $occupe => 'Création en cours — ne fermez pas la page.',
                    $choisie => "Prêt à créer. L'enrichissement se poursuit sur la fiche complète.",
                    default => 'Choisissez une gamme pour activer la création.',
                },
                'libellePrincipal' => $occupe ? 'Création en cours…' : 'Créer et enrichir',
            ],

            'etapesCreation' => self::etapesCreation(),
        ];
    }

    /**
     * @return list<Gamme>
     */
    private static function gammes(string $courante): array
    {
        $gammes = [];

        foreach (self::GAMMES as [$id, $label, $sous, $icone]) {
            $gammes[] = [
                'id' => $id, 'label' => $label, 'sous' => $sous, 'icone' => $icone,
                'active' => $id === $courante,
            ];
        }

        return $gammes;
    }

    /**
     * @param list<string>                     $preselection
     * @param list<array{string, list<string>}> $groupes
     *
     * @return list<Bloc>
     */
    private static function blocs(bool $choisie, bool $erreurs, bool $nomVide, bool $adresseVide,
        array $preselection, array $groupes): array
    {
        $blocs = [];

        foreach (self::BLOCS as $index => [$titre, $description, , $type]) {
            $verrouille = $index > 0 && !$choisie;
            $fautif = $erreurs && ((1 === $index && ($nomVide || $adresseVide))
                || (2 === $index && [] !== $groupes && [] === $preselection));

            $blocs[] = [
                'index' => $index,
                'numero' => (string) ($index + 1),
                'titre' => $titre,
                'description' => $verrouille ? 'Ce bloc dépend de la gamme.' : $description,
                'type' => $verrouille ? 'verrouille' : $type,
                'verrouille' => $verrouille,
                'fautif' => $fautif,
                'etiquette' => $fautif ? 'À corriger' : ((0 === $index && $choisie) ? 'Verrouillé après création' : ''),
                'etiquetteTon' => $fautif ? 'erreur' : 'neutre',
            ];
        }

        return $blocs;
    }

    /**
     * Badge de chaque entrée du rail : « ! » si le bloc est fautif, « OK » s'il
     * est renseigné, « — » s'il attend la gamme, « à faire » sinon.
     *
     * @param list<string> $preselection
     * @param list<string> $sites
     * @param list<string> $manquants
     *
     * @return list<Ancre>
     */
    private static function ancres(bool $choisie, bool $erreurs, bool $nomVide, bool $adresseVide,
        bool $zone, array $preselection, bool $repli, string $email, array $sites, array $manquants): array
    {
        $remplis = [
            $choisie,
            !$nomVide && ($zone || !$adresseVide),
            [] !== $preselection,
            true,
            [] !== $sites,
            $repli || '' !== $email,
            true,
        ];

        $ancres = [];

        foreach (self::BLOCS as $index => [$libelle, , $icone]) {
            $verrouille = $index > 0 && !$choisie;
            $fautif = $erreurs && [] !== $manquants
                && ((1 === $index && ($nomVide || $adresseVide)) || (2 === $index && [] === $preselection));

            $ancres[] = [
                'index' => $index,
                'libelle' => $libelle,
                'icone' => $icone,
                'badge' => $fautif ? '!' : ($remplis[$index] ? 'OK' : ($verrouille ? '—' : 'à faire')),
                'ton' => $fautif ? 'erreur' : ($remplis[$index] ? 'ok' : ($verrouille ? 'inerte' : 'attente')),
                'verrouillee' => $verrouille,
                'active' => 0 === $index,
            ];
        }

        return $ancres;
    }

    /**
     * @param list<array{string, list<string>}> $groupes
     * @param list<string>                      $preselection
     *
     * @return list<GroupeClasse>
     */
    private static function classification(array $groupes, array $preselection, bool $erreurs): array
    {
        $sortie = [];

        foreach ($groupes as [$label, $valeurs]) {
            $puces = [];
            $retenues = 0;

            foreach ($valeurs as $valeur) {
                $active = \in_array($valeur, $preselection, true);
                $retenues += $active ? 1 : 0;
                $puces[] = ['libelle' => $valeur, 'active' => $active];
            }

            $obligatoire = str_contains($label, '*');

            $sortie[] = [
                'label' => $label,
                'compte' => $retenues > 0
                    ? $retenues . ' sélectionné' . ($retenues > 1 ? 's' : '')
                    : 'aucune sélection',
                'fautif' => $erreurs && $obligatoire && 0 === $retenues,
                'puces' => $puces,
            ];
        }

        return $sortie;
    }

    /**
     * @return list<Departement>
     */
    private static function departements(): array
    {
        $sortie = [];

        foreach (self::DEPARTEMENTS as [$numero, $libelle]) {
            $sortie[] = [
                'numero' => $numero,
                'libelle' => $libelle,
                'active' => \in_array($numero, self::DEPARTEMENTS_PRE, true),
            ];
        }

        return $sortie;
    }

    /**
     * @return list<Interrupteur>
     */
    private static function interrupteurs(): array
    {
        return [
            [
                'label' => 'Actif',
                'actif' => true,
                'aide' => 'Une fiche inactive reste dans le référentiel mais disparaît des canaux publics.',
                'etiquette' => '',
            ],
            [
                'label' => 'Adhérent Business Premium',
                'actif' => true,
                'aide' => 'Activé, la mention « recommandé par Business Profilers » apparaît sur les '
                    . 'canaux publics.',
                'etiquette' => 'Premium',
            ],
        ];
    }

    private static function totalSites(): int
    {
        $total = 0;

        foreach (self::SITES as [, $sites]) {
            $total += \count($sites);
        }

        return $total;
    }

    /**
     * @param list<string> $retenus
     *
     * @return list<Puce>
     */
    private static function pucesSites(array $retenus): array
    {
        $puces = [];

        foreach (\array_slice($retenus, 0, self::PUCES_SITES) as $libelle) {
            $puces[] = ['libelle' => $libelle, 'active' => true];
        }

        $reste = \count($retenus) - self::PUCES_SITES;

        if ($reste > 0) {
            $puces[] = ['libelle' => '+' . $reste, 'active' => false];
        }

        return $puces;
    }

    /**
     * @param list<string> $retenus
     *
     * @return list<GroupeSites>
     */
    private static function groupesSites(array $retenus): array
    {
        $groupes = [];

        foreach (self::SITES as [$nom, $sites]) {
            $lignes = [];
            $compte = 0;

            foreach ($sites as [$libelle, $mention]) {
                $retenu = \in_array($libelle, $retenus, true);
                $compte += $retenu ? 1 : 0;

                $lignes[] = [
                    'libelle' => $libelle,
                    'retenu' => $retenu,
                    'verrouille' => 'obligatoire' === $mention,
                    'mention' => $mention,
                ];
            }

            $groupes[] = [
                'nom' => $nom,
                'compte' => $compte . ' / ' . \count($sites),
                'sites' => $lignes,
            ];
        }

        return $groupes;
    }

    /**
     * L'email a un statut à part : son absence n'empêche pas la création, elle
     * se signale en gris et non en rouge.
     *
     * @param array{prenom: string, nom: string, email: string, tel: string} $contact
     *
     * @return list<ChampContact>
     */
    private static function champsContact(array $contact, bool $repli, bool $erreurs): array
    {
        $introuvable = '' === $contact['email'] && !$repli;
        $champs = [];

        foreach ([
            ['Prénom *', $contact['prenom'], 'prenom'],
            ['Nom *', $contact['nom'], 'nom'],
            ['Email', $contact['email'], 'email'],
            ['Téléphone', $contact['tel'], 'tel'],
        ] as [$label, $valeur, $cle]) {
            $vide = '' === $valeur;
            $neutre = 'email' === $cle && $introuvable;
            $fautif = $erreurs && $vide && \in_array($cle, ['prenom', 'nom'], true);

            $champs[] = [
                'label' => $label,
                'valeur' => '' !== $valeur ? $valeur : ($neutre ? 'À rechercher' : '—'),
                'vide' => $vide,
                'fautif' => $fautif,
                'indice' => $neutre
                    ? "Email introuvable pour l'instant — la fiche peut être créée sans."
                    : ($fautif ? 'Ce champ est obligatoire.' : ''),
                'indiceTon' => $neutre ? 'attention' : 'erreur',
            ];
        }

        return $champs;
    }

    /**
     * @return list<OptionAcces>
     */
    private static function optionsAcces(bool $repli): array
    {
        $options = [];

        foreach ([
            ['envoyer', 'Envoyer les accès par email',
                'Le prestataire reçoit ses identifiants du portail dès la création.'],
            ['rien', 'Ne rien envoyer',
                'La fiche rejoindra la file des accès non transmis, à traiter plus tard.'],
        ] as [$id, $label, $aide]) {
            $inerte = $repli && 'envoyer' === $id;
            // Repli activé : c'est « ne rien envoyer » qui est retenu.

            $options[] = [
                'id' => $id,
                'label' => $label,
                'aide' => $inerte
                    ? "Indisponible : le contact de repli n'est pas l'adresse du prestataire."
                    : $aide,
                'active' => $repli ? 'rien' === $id : 'envoyer' === $id,
                'inerte' => $inerte,
            ];
        }

        return $options;
    }

    /**
     * @return list<PartieAdresse>
     */
    private static function partiesAdresse(bool $vide): array
    {
        $parties = [];

        foreach ([
            ['Rue', self::ADRESSE_RUE],
            ['Code postal', self::ADRESSE_CP],
            ['Ville', self::ADRESSE_VILLE],
            ['Pays', self::ADRESSE_PAYS],
            ['Coordonnées', self::ADRESSE_GPS],
        ] as [$cle, $valeur]) {
            $parties[] = ['cle' => $cle, 'valeur' => $vide ? '—' : $valeur];
        }

        return $parties;
    }

    /**
     * @return list<Suggestion>
     */
    private static function suggestions(): array
    {
        $suggestions = [];

        foreach (self::SUGGESTIONS as $rang => [$titre, $detail]) {
            $suggestions[] = ['titre' => $titre, 'detail' => $detail, 'active' => 0 === $rang];
        }

        return $suggestions;
    }

    /**
     * @return list<EtapeCreation>
     */
    private static function etapesCreation(): array
    {
        $etapes = [];

        foreach (self::ETAPES_CREATION as [$libelle, $etat]) {
            $etapes[] = ['libelle' => $libelle, 'etat' => $etat];
        }

        return $etapes;
    }
}
