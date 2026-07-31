<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Liste des fiches — maquette « Liste des fiches ».
 *
 * Un seul écran remplace le référentiel général et la page Lieux : le panneau
 * de filtres porte la gamme, et les colonnes suivent quand une seule gamme est
 * retenue. Treize états de démonstration, servis par la query string.
 *
 * Le point dur du handoff est la sémantique de facettes : **intersection entre
 * groupes, union à l'intérieur d'un groupe**. Un seul prédicat sert les lignes,
 * les badges et le compte, pour qu'aucun des trois ne dise autre chose.
 *
 * @phpstan-type Etat array{id: string, label: string, note: string}
 * @phpstan-type Facette array{label: string, compte: string, retenue: bool}
 * @phpstan-type GroupeFiltre array{nom: string, etiquette: string, facettes: list<Facette>}
 * @phpstan-type Badge array{cle: string, valeur: string}
 * @phpstan-type Colonne array{label: string, largeur: int, alignement: string}
 * @phpstan-type Ligne array{nom: string, sousTitre: string, gamme: string, type: string, pays: string,
 *     statut: string, statutTon: string, completude: int, palier: string, canaux: string,
 *     canauxNuls: bool, actif: bool, premium: bool, quand: string, auteur: string, vignette: bool,
 *     marque: string, selectionnee: bool}
 * @phpstan-type ActionGroupee array{label: string, icone: string, plafond: int, depasse: bool,
 *     irreversible: bool}
 * @phpstan-type Vue array{nom: string, recette: string, proprietaire: string, courante: bool}
 * @phpstan-type GroupeVues array{nom: string, note: string, vues: list<Vue>}
 * @phpstan-type Page array{libelle: string, courante: bool}
 */
final class ListeFichesMaquette
{
    /**
     * Les treize états de démonstration du handoff.
     *
     * @var list<array{string, string, string}>
     */
    public const ETATS = [
        ['nominal', 'Vue nominale', 'Gammes mélangées'],
        ['lieux', 'Filtrée sur Lieux', 'Colonnes propres à la gamme'],
        ['selection', 'Sélection partielle', "Barre d'actions déployée"],
        ['tout', 'Sélection du filtre entier', 'Volume exact affiché'],
        ['seuil', 'Action désactivée par seuil', 'Volume au-delà du plafond'],
        ['socle', 'Valeurs IA et conflits', 'Le socle en contexte tabulaire'],
        ['modifiee', 'Vue enregistrée modifiée', 'Enregistrer ou réinitialiser'],
        ['replie', 'Panneau de filtres replié', 'Rangé après usage'],
        ['picker', 'Sélecteur de vues', 'Choix en un clic'],
        ['rien', 'Filtre sans résultat', "À distinguer d'un référentiel vide"],
        ['chargement', 'Chargement', 'Squelettes de lignes'],
        ['vues', 'Gestion des vues', 'Renommer, partager, défaut'],
        ['compacte', 'Densité compacte', "Plus de lignes à l'écran"],
    ];

    /** Volume total du référentiel, hors filtre. */
    public const TOTAL_REFERENTIEL = 18953;

    /**
     * Nom, ville, gamme, type, pays, statut, complétude, canaux, actif, premium,
     * quand, auteur, vignette, marqueur du socle.
     *
     * @var list<array{string, string, string, string, string, string, int, int, int, int, string, string, int, string}>
     */
    private const FICHES = [
        ['Château de Montvillargenne', 'Gouvieux', 'Lieux', 'Château', 'France', 'En enrichissement', 42, 5, 1, 1, 'il y a 12 min', 'M. Rousseau', 1, 'ia'],
        ['Domaine de Chantilly', 'Chantilly', 'Lieux', 'Domaine', 'France', 'Publiée', 71, 22, 1, 1, 'il y a 1 h', 'C. Berthier', 1, ''],
        ['Pavillon Dauphine', 'Paris 16e', 'Lieux', 'Salle de réception', 'France', 'Publiée', 94, 28, 1, 0, 'il y a 3 h', 'L. Garnier', 1, ''],
        ['Restaurant Villa M', 'Paris 15e', 'Restaurants', 'Bistronomique', 'France', 'Publiée', 78, 19, 1, 1, 'hier', 'C. Berthier', 1, 'conflit'],
        ['Le Cercle Interallié', 'Paris 8e', 'Lieux', 'Hôtel particulier', 'France', 'Validée', 66, 0, 1, 1, 'hier', 'A. Dufour', 1, ''],
        ['Stage de pilotage F3', 'Le Mans', 'Activités', 'Sportif', 'France', 'En enrichissement', 51, 8, 1, 0, 'il y a 2 j', 'S. Moreau', 0, 'ia'],
        ['Traiteur Lenôtre Events', 'Paris 12e', 'Prestataires', 'Traiteur', 'France', 'Publiée', 83, 24, 1, 1, 'il y a 2 j', 'M. Rousseau', 1, ''],
        ['Hôtel Molitor', 'Paris 16e', 'Lieux', 'Hôtel', 'France', 'Publiée', 79, 21, 1, 1, 'il y a 3 j', 'L. Garnier', 1, 'ia'],
        ['Escape Yard Bastille', 'Paris 11e', 'Activités', 'Insolite', 'France', 'Brouillon', 18, 0, 0, 0, 'il y a 4 j', 'S. Moreau', 0, ''],
        ['Karting Wembley', 'Wembley', 'Activités', 'Sportif', 'R.-U.', 'Publiée', 55, 6, 1, 0, 'il y a 5 j', 'A. Dufour', 1, 'conflit'],
        ['Plateau Signature Lenôtre', 'Paris 12e', 'Plateaux repas', 'Plateau repas', 'France', 'Publiée', 88, 12, 1, 1, 'il y a 6 j', 'C. Berthier', 1, ''],
        ['Château de Namur', 'Namur', 'Lieux', 'Château', 'Belgique', 'Archivée', 62, 0, 0, 0, 'il y a 8 j', 'M. Rousseau', 1, ''],
        ['Abbaye des Vaux de Cernay', 'Cernay-la-Ville', 'Lieux', 'Domaine', 'France', 'Publiée', 84, 26, 1, 1, 'il y a 20 min', 'C. Berthier', 1, 'ia'],
        ['Grandes Écuries de Chantilly', 'Chantilly', 'Lieux', 'Château', 'France', 'Publiée', 91, 27, 1, 1, 'il y a 40 min', 'L. Garnier', 1, ''],
        ['Pavillon Royal', 'Paris 16e', 'Lieux', 'Salle de réception', 'France', 'Publiée', 76, 20, 1, 0, 'il y a 2 h', 'M. Rousseau', 1, ''],
        ['Hôtel Salomon de Rothschild', 'Paris 8e', 'Lieux', 'Hôtel particulier', 'France', 'Publiée', 88, 25, 1, 1, 'il y a 4 h', 'A. Dufour', 1, 'conflit'],
        ['Domaine de Verchant', 'Montpellier', 'Lieux', 'Domaine', 'France', 'Publiée', 73, 18, 1, 1, 'il y a 5 h', 'C. Berthier', 1, ''],
        ['Château de la Napoule', 'Mandelieu', 'Lieux', 'Château', 'France', 'Publiée', 68, 14, 1, 0, 'il y a 6 h', 'S. Moreau', 1, 'ia'],
        ['Les Salons Hoche', 'Paris 8e', 'Lieux', 'Salle de réception', 'France', 'Publiée', 82, 22, 1, 1, 'hier', 'L. Garnier', 1, ''],
        ['Villa Ephrussi', 'Saint-Jean-Cap-Ferrat', 'Lieux', 'Domaine', 'France', 'Publiée', 79, 21, 1, 1, 'hier', 'M. Rousseau', 1, ''],
        ['InterContinental Lyon', 'Lyon', 'Lieux', 'Hôtel', 'France', 'Publiée', 93, 29, 1, 1, 'hier', 'A. Dufour', 1, ''],
        ['Château de Berne', 'Lorgues', 'Lieux', 'Domaine', 'France', 'Publiée', 70, 16, 1, 0, 'il y a 2 j', 'C. Berthier', 1, 'conflit'],
        ['Le Pavillon Élysée', 'Paris 8e', 'Lieux', 'Salle de réception', 'France', 'Publiée', 64, 11, 1, 0, 'il y a 2 j', 'S. Moreau', 0, 'ia'],
        ['Domaine de Fontenille', 'Lauris', 'Lieux', 'Domaine', 'France', 'Publiée', 86, 24, 1, 1, 'il y a 3 j', 'L. Garnier', 1, ''],
        ["Château d'Augerville", 'Augerville-la-Rivière', 'Lieux', 'Château', 'France', 'Publiée', 77, 19, 1, 0, 'il y a 3 j', 'M. Rousseau', 1, ''],
        ['Hôtel du Collectionneur', 'Paris 8e', 'Lieux', 'Hôtel', 'France', 'Publiée', 90, 26, 1, 1, 'il y a 4 j', 'A. Dufour', 1, ''],
        ['Bastide de Gordes', 'Gordes', 'Lieux', 'Hôtel', 'France', 'Publiée', 81, 23, 1, 1, 'il y a 4 j', 'C. Berthier', 1, 'ia'],
        ['Château de Vault-de-Lugny', 'Avallon', 'Lieux', 'Château', 'France', 'Publiée', 59, 9, 1, 0, 'il y a 5 j', 'S. Moreau', 0, ''],
        ['Brasserie Lipp', 'Paris 6e', 'Restaurants', 'Brasserie', 'France', 'Publiée', 85, 23, 1, 1, 'il y a 5 j', 'L. Garnier', 1, 'ia'],
        ['Restaurant Le Chiberta', 'Paris 8e', 'Restaurants', 'Gastronomique', 'France', 'Publiée', 74, 17, 1, 0, 'il y a 6 j', 'M. Rousseau', 1, ''],
        ['Accrobranche Fontainebleau', 'Fontainebleau', 'Activités', 'Sportif', 'France', 'Publiée', 61, 10, 1, 0, 'il y a 6 j', 'A. Dufour', 1, 'conflit'],
        ['Atelier dégustation Bordeaux', 'Bordeaux', 'Activités', 'Dégustation', 'France', 'Publiée', 67, 13, 1, 1, 'il y a 7 j', 'C. Berthier', 1, ''],
        ['Traiteur Potel et Chabot', 'Paris 15e', 'Prestataires', 'Traiteur', 'France', 'Publiée', 89, 25, 1, 1, 'il y a 7 j', 'S. Moreau', 1, 'ia'],
        ['Audiovisuel Novelty', 'Villepinte', 'Prestataires', 'Audiovisuel', 'France', 'Publiée', 72, 15, 1, 0, 'il y a 9 j', 'L. Garnier', 0, ''],
        ['Buffet Signature Dalloyau', 'Paris 8e', 'Plateaux repas', 'Buffet', 'France', 'Publiée', 83, 21, 1, 1, 'il y a 9 j', 'M. Rousseau', 1, ''],
        ['Château de Genève', 'Genève', 'Château', 'Château', 'Suisse', 'Validée', 65, 0, 1, 0, 'il y a 11 j', 'A. Dufour', 1, 'conflit'],
    ];

    /** Clé de groupe → intitulé, clé de badge, facettes (libellé + volume). */
    private const GAMME = 'Gamme';
    private const STATUT = 'Statut du cycle de vie';
    private const PALIER = 'Complétude';
    private const DIFFUSION = 'Diffusion par canal';
    private const GEO = 'Pays et région';
    private const ATTRIBUTS = 'Attributs';
    private const FILES = 'Files de travail';
    private const TAXONOMIE = 'Catégorie et thématiques';
    private const DATES = 'Dates et contributeur';

    /** @var array<string, list<array{string, string}>> */
    private const FACETTES = [
        self::GAMME => [
            ['Lieux', '12 480'], ['Restaurants', '3 210'], ['Activités', '1 640'],
            ['Prestataires de services', '2 205'], ['Plateaux repas', '418'],
        ],
        self::STATUT => [
            ['Brouillon', '412'], ['En enrichissement', '1 268'], ['Validée', '784'],
            ['Publiée', '15 906'], ['Archivée', '583'],
        ],
        self::PALIER => [
            ['Complet ≥ 75 %', '8 412'], ['Publiable 60-74 %', '6 208'], ['Insuffisant < 60 %', '4 333'],
        ],
        self::DIFFUSION => [
            ['Sur 20 canaux et plus', '6 104'], ['Sur 5 à 19 canaux', '7 388'],
            ['Sur moins de 5 canaux', '2 414'], ['Absente de tous les canaux', '3 047'],
        ],
        self::GEO => [
            ['France', '16 210'], ['Belgique', '902'], ['Suisse', '784'], ['Espagne', '638'],
            ['Italie', '471'], ['Royaume-Uni', '368'],
        ],
        self::ATTRIBUTS => [
            ['Actif', '17 402'], ['Inactif', '1 551'], ['Adhérent Business Premium', '4 218'],
        ],
        self::FILES => [
            ['Contact de repli', '1 284'], ['Accès extranet non transmis', '9'],
            ['Anomalies de gouvernance', '31'], ['Valeurs IA en attente', '200'],
        ],
        self::TAXONOMIE => [
            ['Château', '3 204'], ['Domaine', '1 810'], ['Hôtel', '4 128'],
            ['Salle de réception', '2 240'], ['Historique', '5 402'], ['Nature', '4 118'],
        ],
        self::DATES => [
            ['Créées cette semaine', '186'], ['Modifiées aujourd\'hui', '428'],
            ['Sans modification depuis 6 mois', '2 940'],
        ],
    ];

    /** @var array<string, string> Intitulé court du badge, par groupe */
    private const CLES_BADGE = [
        self::GAMME => 'Gamme',
        self::STATUT => 'Statut',
        self::GEO => 'Pays',
        self::PALIER => 'Complétude',
        self::FILES => 'File',
    ];

    /** @var array<string, array{string, string}> Teinte du texte et du fond, par statut */
    private const TONS_STATUT = [
        'Brouillon' => ['neutral-500', 'neutral-200'],
        'En enrichissement' => ['primary-marine', 'secondary-p-che-p-le'],
        'Validée' => ['primary-marine', 'mdm-surface-selected'],
        'Publiée' => ['primary-marine', 'secondary-vert-p-le'],
        'Archivée' => ['neutral-500', 'neutral-200'],
    ];

    /**
     * Actions toujours visibles. Le plafond est le volume au-delà duquel
     * l'action est désactivée et réclame une confirmation d'un autre ordre.
     *
     * @var list<array{string, string, int}>
     */
    private const ACTIONS_PRINCIPALES = [
        ['Changer le statut', 'check-circle', 0],
        ['Publier', 'eye', 5000],
        ['Contributeur', 'users', 0],
        ['Exporter', 'upload-simple', 0],
    ];

    /**
     * Tout le reste — et tout ce qui est irréversible ou de masse — derrière un
     * « Plus d'actions » explicitement nommé.
     *
     * @var list<array{string, string, int, bool}>
     */
    private const ACTIONS_SECONDAIRES = [
        ['Dépublier', 'lock', 5000, true],
        ['Attribuer la visibilité', 'images', 0, false],
        ['Lancer une campagne IA', 'rocket', 2000, false],
        ['Envoyer les accès extranet', 'note', 500, true],
    ];

    /** @var list<array{string, string, list<array{string, string, string, bool}>}> */
    private const VUES = [
        ['Personnelles', 'Visibles de vous seule', [
            ['Ma file de validation', 'Statut validée · complétude < 75 % · tri par ancienneté', 'Marie', true],
            ['Qualité des Lieux France', 'Lieux · France · anomalies · 9 colonnes qualité', 'Marie', false],
            ['Suivi de mes créations', 'Contributeur = moi · créées ce mois', 'Marie', false],
        ]],
        ['Partagées par l\'équipe', 'Modifiables par leur auteur', [
            ['Campagne régionale Sud', 'Provence · Occitanie · publiée · Premium', 'C. Berthier', false],
            ['Accès extranet à traiter', 'Accès non transmis · contact renseigné', 'A. Dufour', false],
            ['Suggestions IA à arbitrer', 'Valeurs IA en attente · confiance élevée', 'M. Rousseau', false],
            ['Référentiel Belgique', 'Belgique · toutes gammes · 11 colonnes', 'L. Garnier', false],
        ]],
    ];

    /** Nombre de lignes par page, selon la densité. */
    private const PAGE_NORMALE = 14;
    private const PAGE_COMPACTE = 19;

    /** Les quatre premières lignes de la page sont cochées en sélection partielle. */
    private const SELECTION_PARTIELLE = 4;

    public static function etatValide(string $etat): string
    {
        foreach (self::ETATS as [$id]) {
            if ($id === $etat) {
                return $etat;
            }
        }

        return 'nominal';
    }

    /**
     * @return Etat
     */
    public static function etat(string $etat): array
    {
        $etat = self::etatValide($etat);

        foreach (self::ETATS as [$id, $label, $note]) {
            if ($id === $etat) {
                return ['id' => $id, 'label' => $label, 'note' => $note];
            }
        }

        return ['id' => 'nominal', 'label' => 'Vue nominale', 'note' => 'Gammes mélangées'];
    }

    /**
     * Facettes retenues par l'état. C'est la seule source des filtres : lignes,
     * badges et compte en découlent tous les trois.
     *
     * @return list<string>
     */
    private static function selection(string $etat): array
    {
        return match ($etat) {
            'lieux' => ['Lieux', 'Publiée', 'France'],
            'socle' => ['Valeurs IA en attente', 'Anomalies de gouvernance'],
            'rien' => ['Plateaux repas', 'Italie', 'Archivée', 'Insuffisant < 60 %', 'Accès extranet non transmis'],
            'chargement' => ['Publiée', 'France'],
            default => ['Publiée', 'France'],
        };
    }

    /**
     * Prédicat d'une facette sur une fiche. Intersection entre groupes, union à
     * l'intérieur d'un groupe : la sémantique de facettes attendue.
     *
     * @param array{string, string, string, string, string, string, int, int, int, int, string, string, int, string} $fiche
     */
    private static function correspond(string $groupe, string $facette, array $fiche): bool
    {
        return match ($groupe) {
            self::GAMME => str_starts_with($facette, $fiche[2]) || str_starts_with($fiche[2], $facette),
            self::STATUT => $fiche[5] === $facette,
            self::PALIER => match (true) {
                str_starts_with($facette, 'Complet') => $fiche[6] >= 75,
                str_starts_with($facette, 'Publiable') => $fiche[6] >= 60 && $fiche[6] < 75,
                default => $fiche[6] < 60,
            },
            self::DIFFUSION => match (true) {
                str_contains($facette, '20 canaux') => $fiche[7] >= 20,
                str_contains($facette, '5 à 19') => $fiche[7] >= 5 && $fiche[7] < 20,
                str_contains($facette, 'moins de 5') => $fiche[7] > 0 && $fiche[7] < 5,
                default => 0 === $fiche[7],
            },
            self::GEO => $fiche[4] === $facette || ('Royaume-Uni' === $facette && 'R.-U.' === $fiche[4]),
            self::ATTRIBUTS => match ($facette) {
                'Actif' => 1 === $fiche[8],
                'Inactif' => 0 === $fiche[8],
                default => 1 === $fiche[9],
            },
            self::FILES => match (true) {
                str_starts_with($facette, 'Valeurs IA') => 'ia' === $fiche[13],
                str_starts_with($facette, 'Anomalies') => 'conflit' === $fiche[13],
                default => false,
            },
            self::TAXONOMIE => $fiche[3] === $facette,
            default => true,
        };
    }

    /**
     * Volume affiché : le plus petit total de groupe, comme la maquette — un
     * échantillon de 36 fiches ne peut pas prétendre compter 18 953 entrées.
     *
     * @param list<string> $retenues
     */
    private static function volume(array $retenues, int $lignes): int
    {
        if (0 === $lignes) {
            return 0;
        }

        $minimum = null;

        foreach (self::FACETTES as $groupe => $facettes) {
            $somme = 0;

            foreach ($facettes as [$label, $compte]) {
                if (\in_array($label, $retenues, true)) {
                    $somme += (int) str_replace(' ', '', $compte);
                }
            }

            if ($somme > 0) {
                $minimum = null === $minimum ? $somme : min($minimum, $somme);
            }
        }

        return $minimum ?? self::TOTAL_REFERENTIEL;
    }

    /**
     * Tout ce que le gabarit consomme.
     *
     * @return array<string, mixed>
     */
    public static function vue(string $etat): array
    {
        $etat = self::etatValide($etat);

        $surLieux = 'lieux' === $etat;
        $socle = 'socle' === $etat;
        $rien = 'rien' === $etat;
        $chargement = 'chargement' === $etat;
        $compacte = 'compacte' === $etat;
        $replie = 'replie' === $etat;
        $picker = 'picker' === $etat;
        $vues = 'vues' === $etat;
        $modifiee = 'modifiee' === $etat;
        $toutLeFiltre = \in_array($etat, ['tout', 'seuil'], true);
        $selection = $toutLeFiltre || \in_array($etat, ['selection'], true);

        $retenues = self::selection($etat);
        $correspondantes = self::lignesFiltrees($retenues);

        $parPage = $compacte ? self::PAGE_COMPACTE : self::PAGE_NORMALE;
        $page = \array_slice($correspondantes, 0, $parPage);
        $total = self::volume($retenues, \count($correspondantes));

        $cochees = $toutLeFiltre ? \count($page) : ($selection ? min(self::SELECTION_PARTIELLE, \count($page)) : 0);
        $compteSelection = $toutLeFiltre ? $total : $cochees;

        return [
            'etat' => self::etat($etat),
            'etats' => self::ETATS,
            'surLieux' => $surLieux,
            'compacte' => $compacte,
            'socle' => $socle,

            'panneau' => [
                'replie' => $replie,
                'groupes' => self::groupes($retenues, $surLieux),
                'compte' => \count($retenues),
                'resume' => \count($retenues) . (\count($retenues) > 1 ? ' filtres actifs · ' : ' filtre actif · ')
                    . self::nombre($total) . ' résultats',
            ],

            'recherche' => [
                'valeur' => $socle ? 'chât' : 'Rechercher une fiche',
                'saisie' => $socle,
            ],

            'vueCourante' => [
                'nom' => $surLieux ? 'Qualité des Lieux France' : 'Ma file de validation',
                'modifiee' => $modifiee,
            ],
            'picker' => ['ouvert' => $picker, 'vues' => self::vuesAPlat()],
            'gestionVues' => ['ouverte' => $vues, 'groupes' => self::groupesVues()],
            'colonnes' => [
                'libelle' => $surLieux ? '13 colonnes · 4 propres aux Lieux' : '13 colonnes',
            ],

            'bandeau' => [
                'selection' => $selection,
                'compte' => self::nombre($total) . ($total > 1 ? ' fiches' : ' fiche'),
                'badges' => self::badges($retenues),
                'tri' => 'Tri : dernière modification ↓',
                'compteSelection' => $toutLeFiltre
                    ? self::nombre($compteSelection) . ' fiches sélectionnées'
                    : $cochees . ' sélectionnées sur ' . self::nombre($total),
                'proposerTout' => $selection && !$toutLeFiltre,
                'libelleTout' => 'Sélectionner les ' . self::nombre($total) . ' fiches du filtre',
                'toutRetenu' => $toutLeFiltre,
                'avertissement' => self::nombre($compteSelection) . ' fiches seront modifiées',
                'menuOuvert' => \in_array($etat, ['seuil', 'selection'], true),
            ],

            'actions' => [
                'principales' => self::actionsPrincipales($compteSelection),
                'secondaires' => self::actionsSecondaires($compteSelection),
            ],

            'tableau' => [
                'colonnes' => self::colonnes($surLieux, $compacte),
                'lignes' => self::lignes($page, $cochees, $socle),
                'toutCoche' => $toutLeFiltre,
                'partiel' => $selection && !$toutLeFiltre,
                'afficher' => !$chargement && !$rien,
                'squelette' => $chargement,
                'vide' => $rien,
            ],

            'pagination' => [
                'note' => $rien
                    ? 'Aucun résultat à paginer'
                    : 'Affichage 1 – ' . \count($page) . ' sur ' . self::nombre($total)
                        . ($compteSelection > 0 ? ' · ' . self::nombre($compteSelection) . ' sélectionnées' : ''),
                'pages' => self::pages($total, $parPage),
                'total' => 'sur ' . self::nombre(max(1, (int) ceil($total / $parPage))),
            ],
        ];
    }

    /**
     * @param list<string> $retenues
     *
     * @return list<array{string, string, string, string, string, string, int, int, int, int, string, string, int, string}>
     */
    private static function lignesFiltrees(array $retenues): array
    {
        $lignes = [];

        foreach (self::FICHES as $fiche) {
            $garde = true;

            foreach (self::FACETTES as $groupe => $facettes) {
                $duGroupe = [];

                foreach ($facettes as [$label]) {
                    if (\in_array($label, $retenues, true)) {
                        $duGroupe[] = $label;
                    }
                }

                if ([] === $duGroupe) {
                    continue;
                }

                $une = false;

                foreach ($duGroupe as $facette) {
                    if (self::correspond($groupe, $facette, $fiche)) {
                        $une = true;
                        break;
                    }
                }

                if (!$une) {
                    $garde = false;
                    break;
                }
            }

            if ($garde) {
                $lignes[] = $fiche;
            }
        }

        return $lignes;
    }

    /**
     * La taxonomie n'apparaît que si une seule gamme est filtrée : le panneau
     * n'offre pas quatre axes presque vides.
     *
     * @param list<string> $retenues
     *
     * @return list<GroupeFiltre>
     */
    private static function groupes(array $retenues, bool $surLieux): array
    {
        $groupes = [];

        foreach (self::FACETTES as $nom => $facettes) {
            if (self::TAXONOMIE === $nom && !$surLieux) {
                continue;
            }

            $lignes = [];

            foreach ($facettes as [$label, $compte]) {
                $lignes[] = [
                    'label' => $label,
                    'compte' => $compte,
                    'retenue' => \in_array($label, $retenues, true),
                ];
            }

            $groupes[] = [
                'nom' => $nom,
                'etiquette' => self::TAXONOMIE === $nom ? 'Gamme Lieux' : '',
                'facettes' => $lignes,
            ];
        }

        return $groupes;
    }

    /**
     * @param list<string> $retenues
     *
     * @return list<Badge>
     */
    private static function badges(array $retenues): array
    {
        $badges = [];

        foreach ($retenues as $label) {
            $cle = 'Filtre';

            foreach (self::CLES_BADGE as $groupe => $court) {
                foreach (self::FACETTES[$groupe] as [$facette]) {
                    if ($facette === $label) {
                        $cle = $court;
                        break 2;
                    }
                }
            }

            $badges[] = ['cle' => $cle, 'valeur' => $label];
        }

        return $badges;
    }

    /**
     * La colonne polymorphe : « Type » devient « Catégorie de lieu » quand la
     * liste ne porte qu'une gamme.
     *
     * @return list<Colonne>
     */
    private static function colonnes(bool $surLieux, bool $compacte): array
    {
        $colonnes = [];

        foreach ([
            ['', $compacte ? 30 : 40, 'left'],
            ['Nom et ville', $compacte ? 250 : 280, 'left'],
            ['Gamme', 106, 'left'],
            [$surLieux ? 'Catégorie de lieu' : 'Type', 168, 'left'],
            ['Pays', 82, 'left'],
            ['Statut', 130, 'left'],
            ['Complétude', 132, 'left'],
            ['Diffusion', 92, 'right'],
            ['Actif', 52, 'center'],
            ['BP', 58, 'center'],
            ['Dernière modification', 164, 'left'],
            ['', 104, 'right'],
        ] as [$label, $largeur, $alignement]) {
            $colonnes[] = ['label' => $label, 'largeur' => $largeur, 'alignement' => $alignement];
        }

        return $colonnes;
    }

    /**
     * L'identifiant appartient à la fiche, pas à sa position : il se dérive du
     * rang fixe de l'entrée, donc reste stable d'un filtre et d'une page à
     * l'autre.
     *
     * @param list<array{string, string, string, string, string, string, int, int, int, int, string, string, int, string}> $page
     *
     * @return list<Ligne>
     */
    private static function lignes(array $page, int $cochees, bool $socle): array
    {
        $lignes = [];

        foreach ($page as $rang => $fiche) {
            [$nom, $ville, $gamme, $type, $pays, $statut, $pct, $canaux, $actif, $premium,
                $quand, $auteur, $vignette, $marque] = $fiche;

            $reference = 'MDM-' . (482001 + (int) array_search($fiche, self::FICHES, true));

            $lignes[] = [
                'nom' => $nom,
                'sousTitre' => $ville . ' · ' . $reference,
                'gamme' => $gamme,
                'type' => $type,
                'pays' => $pays,
                'statut' => $statut,
                'statutTon' => implode(' ', self::TONS_STATUT[$statut] ?? ['neutral-500', 'neutral-200']),
                'completude' => $pct,
                'palier' => $pct >= 75 ? 'complet' : ($pct >= 60 ? 'publiable' : 'insuffisant'),
                'canaux' => $canaux . ' / 30',
                'canauxNuls' => 0 === $canaux,
                'actif' => 1 === $actif,
                'premium' => 1 === $premium,
                'quand' => $quand,
                'auteur' => $auteur,
                'vignette' => 1 === $vignette,
                // Le marqueur du socle ne paraît que dans l'état qui le montre.
                'marque' => $socle ? $marque : '',
                'selectionnee' => $rang < $cochees,
            ];
        }

        return $lignes;
    }

    /**
     * @return list<ActionGroupee>
     */
    private static function actionsPrincipales(int $compte): array
    {
        $actions = [];

        foreach (self::ACTIONS_PRINCIPALES as [$label, $icone, $plafond]) {
            $actions[] = [
                'label' => $label,
                'icone' => $icone,
                'plafond' => $plafond,
                'depasse' => $plafond > 0 && $compte > $plafond,
                'irreversible' => false,
            ];
        }

        return $actions;
    }

    /**
     * @return list<ActionGroupee>
     */
    private static function actionsSecondaires(int $compte): array
    {
        $actions = [];

        foreach (self::ACTIONS_SECONDAIRES as [$label, $icone, $plafond, $irreversible]) {
            $actions[] = [
                'label' => $label,
                'icone' => $icone,
                'plafond' => $plafond,
                'depasse' => $plafond > 0 && $compte > $plafond,
                'irreversible' => $irreversible,
            ];
        }

        return $actions;
    }

    /**
     * @return list<Vue>
     */
    private static function vuesAPlat(): array
    {
        $vues = [];

        foreach (self::VUES as [, , $liste]) {
            foreach ($liste as [$nom, $recette, $proprietaire, $courante]) {
                $vues[] = [
                    'nom' => $nom, 'recette' => $recette,
                    'proprietaire' => $proprietaire, 'courante' => $courante,
                ];
            }
        }

        return $vues;
    }

    /**
     * @return list<GroupeVues>
     */
    private static function groupesVues(): array
    {
        $groupes = [];

        foreach (self::VUES as [$nom, $note, $liste]) {
            $vues = [];

            foreach ($liste as [$vnom, $recette, $proprietaire, $courante]) {
                $vues[] = [
                    'nom' => $vnom, 'recette' => $recette,
                    'proprietaire' => $proprietaire, 'courante' => $courante,
                ];
            }

            $groupes[] = ['nom' => $nom, 'note' => $note, 'vues' => $vues];
        }

        return $groupes;
    }

    /**
     * @return list<Page>
     */
    private static function pages(int $total, int $parPage): array
    {
        $dernière = max(1, (int) ceil($total / $parPage));
        $pages = [];

        foreach (['‹', '1', '2', '3', '…', (string) $dernière, '›'] as $libelle) {
            $pages[] = ['libelle' => $libelle, 'courante' => '1' === $libelle];
        }

        return $pages;
    }

    /** Espace fine insécable tous les trois chiffres, comme la maquette. */
    private static function nombre(int $valeur): string
    {
        return (string) preg_replace('/(\d)(?=(\d{3})+$)/', '$1 ', (string) $valeur);
    }
}
