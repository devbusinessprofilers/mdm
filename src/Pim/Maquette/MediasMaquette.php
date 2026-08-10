<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de l'écran « Médias » — le DAM.
 *
 * Maquette : `MDM prototype.dc.html`, page `dam`.
 *
 * Un média n'est pas un fichier, c'est un **actif porteur d'un droit d'usage**,
 * décliné par canal et synchronisé avec le PIM. Huit onglets : le stock et son
 * import, la retouche, ce que l'IA en déduit, ses métadonnées, ses formats et
 * leur CDN, ses droits et leur consentement, ses doublons, et son miroir avec
 * le PIM.
 *
 * Seules les constantes viennent du handoff ; totaux, parts et pieds de tableau
 * sont dérivés.
 *
 * @phpstan-type Onglet array{cle: string, libelle: string, icone: string, badge: string, actif: bool}
 * @phpstan-type Cellule array{type: string, texte: string, note: string, gras: bool,
 *     discret: bool, aligne: string, teinte: string, fond: string, part: int}
 * @phpstan-type Colonne array{libelle: string, aligne: string}
 * @phpstan-type Carte array{titre: string, texte: string, valeur: string, icone: string,
 *     lien: string, alerte: bool}
 * @phpstan-type Indicateur array{libelle: string, valeur: string, note: string,
 *     icone: string, fond: string}
 * @phpstan-type Axe array{libelle: string, part: int, poids: string, fond: string}
 * @phpstan-type Sante array{titre: string, titreFaibles: string, score: int, ligne: string,
 *     axes: list<Axe>, faibles: list<array{libelle: string, poids: string}>}
 * @phpstan-type Vue array{cle: string, titre: string, sousTitre: string, onglets: list<Onglet>,
 *     sante: Sante|null, cartes: list<Carte>, indicateurs: list<Indicateur>,
 *     colonnes: list<Colonne>, lignes: list<array{cellules: list<Cellule>}>, pied: string,
 *     actions: list<array{string, string, bool}>, filtres: list<array{string, string}>,
 *     resultat: string, tri: string, note: string}
 */
final class MediasMaquette
{
    public const ONGLET_PAR_DEFAUT = 'biblio';

    /** @var list<string> */
    private const ONGLETS = ['biblio', 'import', 'ia', 'meta', 'formats', 'droits', 'doublons', 'sync'];

    /**
     * Régime de droits, médias, fond, ce qu'il autorise.
     *
     * @var list<array{string, int, string, string}>
     */
    private const DROITS = [
        ['Libre de droits', 98420, 'bg-success-pastel', 'diffusable partout, sans mention'],
        ['Crédit obligatoire', 38614, 'bg-primary-4', 'diffusable avec la mention du photographe'],
        ['Usage interne', 5344, 'bg-neutral-200', 'jamais diffusé sur un canal tiers'],
        ['Sans droits déclarés', 428, 'bg-error-pastel', 'bloqué à la diffusion jusqu\'à déclaration'],
    ];

    /**
     * Type, extensions, médias, ce que le DAM en fait.
     *
     * @var list<array{string, string, int, string}>
     */
    private const TYPES = [
        ['Image', 'JPEG · PNG · WebP', 128410, 'Recadrage, rotation, compression, filtres'],
        ['PDF', 'PDF', 9204, 'OCR du contenu, vignette de première page'],
        ['Vidéo', 'MP4 · MOV', 2418, 'Vignette extraite, transcodage web'],
        ['Présentation', 'PPT · PPTX', 1642, 'Vignette, extraction du texte'],
        ['Document', 'DOC · DOCX', 842, 'Extraction du texte pour recherche'],
        ['Tableur', 'XLS · XLSX', 290, 'Extraction des tableaux tarifaires'],
    ];

    /**
     * Traitement, médias traités, précision, déclenchement.
     *
     * @var list<array{string, int, string, string}>
     */
    private const IA = [
        ['Classification par type', 128410, '96 %', "automatique à l'import"],
        ['Tagging des métadonnées', 118204, '91 %', "automatique à l'import"],
        ['Détection de doublons', 142806, '98 %', 'balayage nocturne'],
        ['OCR sur PDF', 9204, '94 %', "automatique à l'import"],
        ['Extraction de texte', 2774, '88 %', 'PPT, DOC, XLS'],
    ];

    /**
     * Outil, ce qu'il fait, médias retouchés ce mois.
     *
     * @var list<array{string, string, int}>
     */
    private const RETOUCHE = [
        ['Recadrage', 'Ratios libres ou imposés par canal', 4218],
        ['Rotation', 'Par quarts de tour ou angle libre', 1842],
        ['Compression', 'Qualité cible ou poids maximal', 12406],
        ['Filtres photo', 'Luminosité, contraste, saturation, netteté', 3104],
    ];

    /**
     * Format, dimensions, usage, poids moyen.
     *
     * @var list<array{string, string, string, string}>
     */
    private const FORMATS = [
        ['Miniature', '160 × 120', 'Listes et vignettes', '8 Ko'],
        ['Carte', '400 × 300', 'Cartes de fiche', '42 Ko'],
        ['Détail', '800 × 600', 'Portail prestataire', '128 Ko'],
        ['Grand', '1600 × 900', 'Marketplace BP', '310 Ko'],
        ['Très grand', '2000 × 1125', 'Régies et médias', '480 Ko'],
    ];

    /** Nombre de canaux servis par le CDN. */
    private const CANAUX = 5;

    /** Déclinaisons que Kactus et Bedouk attendent encore. */
    private const DECLINAISONS_MANQUANTES = 50990;

    /**
     * Média, fiches concernées, similarité, décision proposée.
     *
     * @var list<array{string, int, string, string}>
     */
    private const DOUBLONS = [
        ['Façade principale · Château de Montvillargenne', 3, '98 %', 'Garder la plus lourde'],
        ["Salon Pleyel · vue d'ensemble", 2, '96 %', 'Garder la plus récente'],
        ['Restaurant · salle principale', 4, '94 %', 'Garder la plus lourde'],
        ['Vue aérienne du domaine', 2, '91 %', 'Vérifier à la main'],
        ['Chambre Deluxe · lit', 3, '89 %', 'Vérifier à la main'],
    ];

    /**
     * Quand, fiche, portée, preuve du consentement.
     *
     * @var list<array{string, string, string, string}>
     */
    private const CONSENTEMENTS = [
        ['3 août 09:12', 'Château de Montvillargenne', '24 visuels', 'Cession signée · PDF'],
        ['28 juillet 15:22', 'Domaine de Chantilly', '18 visuels', 'Cession signée · PDF'],
        ['24 juillet 10:08', 'Pavillon Dauphine', '12 visuels', 'Accord par email'],
        ['18 juillet 16:41', 'Restaurant Villa M', '9 visuels', 'Cession signée · PDF'],
        ['12 juillet 11:30', 'Hôtel Molitor', '31 visuels', 'Accord par email'],
    ];

    /**
     * Sens, ce qui circule, volume, dernier passage, état.
     *
     * @var list<array{string, string, int, string, string}>
     */
    private const SYNCHRONISATIONS = [
        ['DAM → PIM', 'Médias rattachés aux fiches', 142806, 'cette nuit à 02:14', 'à jour'],
        ['PIM → DAM', 'Suppressions de fiches à propager', 38, 'cette nuit à 02:14', 'à jour'],
        ['DAM → Marketplace', 'Déclinaisons et URLs CDN', 142806, 'il y a 2 h', 'à jour'],
        ['DAM → Portail', 'Médias validés par le prestataire', 128410, 'il y a 2 h', 'à jour'],
        ['DAM → Canaux tiers', 'Déclinaisons par canal', 91614, 'il y a 6 h', '50 990 en attente'],
    ];

    /**
     * Nom, fiche, type, poids, droits, diffusable.
     *
     * @var list<array{string, string, string, string, string, bool}>
     */
    private const MEDIAS = [
        ['Façade principale', 'Château de Montvillargenne', 'Photo', '4,2 Mo', 'Libre de droits', true],
        ['Salle de bal', 'Château de Montvillargenne', 'Photo', '3,8 Mo', 'Libre de droits', true],
        ['Plan des étages', 'Château de Montvillargenne', 'Plan PDF', '1,1 Mo', 'Usage interne', false],
        ['Vue aérienne', 'Domaine de Chantilly', 'Photo', '6,4 Mo', 'Crédit obligatoire', true],
        ['Brochure séminaires', 'Domaine de Chantilly', 'Support', '8,2 Mo', 'Libre de droits', false],
        ['Terrasse', 'Restaurant Villa M', 'Photo', '2,9 Mo', 'Libre de droits', true],
    ];

    public static function ongletValide(string $onglet): string
    {
        return \in_array($onglet, self::ONGLETS, true) ? $onglet : self::ONGLET_PAR_DEFAUT;
    }

    /**
     * @return Vue
     */
    public static function vue(string $onglet): array
    {
        $onglet = self::ongletValide($onglet);

        return match ($onglet) {
            'import' => self::import($onglet),
            'ia' => self::ia($onglet),
            'meta' => self::metadonnees($onglet),
            'formats' => self::formats($onglet),
            'droits' => self::droits($onglet),
            'doublons' => self::doublons($onglet),
            'sync' => self::synchronisation($onglet),
            default => self::bibliotheque($onglet),
        };
    }

    /** Le stock total, somme des régimes de droits. */
    public static function total(): int
    {
        $total = 0;

        foreach (self::DROITS as [, $compte]) {
            $total += $compte;
        }

        return $total;
    }

    /** Médias retouchés ce mois, tous outils confondus. */
    private static function retouches(): int
    {
        $total = 0;

        foreach (self::RETOUCHE as [, , $compte]) {
            $total += $compte;
        }

        return $total;
    }

    /** Médias en trop : chaque groupe garde un exemplaire. */
    private static function enDouble(): int
    {
        $total = 0;

        foreach (self::DOUBLONS as [, $copies]) {
            $total += $copies - 1;
        }

        return $total;
    }

    /** Médias qui peuvent partir sur un canal tiers. */
    private static function diffusables(): int
    {
        return self::DROITS[0][1] + self::DROITS[1][1];
    }

    /**
     * @return list<Onglet>
     */
    private static function onglets(string $actif): array
    {
        $entrees = [
            ['biblio', 'Bibliothèque', 'images', self::nombre(self::total())],
            ['import', 'Import & retouche', 'upload-simple', self::nombre(self::retouches())],
            ['ia', 'Reconnaissance IA', 'rocket', (string) \count(self::IA)],
            ['meta', 'Métadonnées & types', 'note', (string) \count(self::TYPES)],
            ['formats', 'Formats & CDN', 'layout', (string) \count(self::FORMATS)],
            ['droits', 'Droits & consentement', 'lock', self::nombre(self::DROITS[3][1])],
            ['doublons', 'Doublons', 'warning', (string) self::enDouble()],
            ['sync', 'Synchronisation PIM', 'spinner', (string) \count(self::SYNCHRONISATIONS)],
        ];

        $onglets = [];

        foreach ($entrees as [$cle, $libelle, $icone, $badge]) {
            $onglets[] = [
                'cle' => $cle, 'libelle' => $libelle, 'icone' => $icone,
                'badge' => $badge, 'actif' => $cle === $actif,
            ];
        }

        return $onglets;
    }

    /**
     * Le squelette commun : ce que tous les onglets partagent.
     *
     * @return Vue
     */
    private static function base(string $onglet, string $titre, string $sousTitre): array
    {
        return [
            'cle' => $onglet,
            'titre' => $titre,
            'sousTitre' => $sousTitre,
            'onglets' => self::onglets($onglet),
            'sante' => null,
            'cartes' => [],
            'indicateurs' => [],
            'colonnes' => [],
            'lignes' => [],
            'pied' => '',
            'actions' => [],
            'filtres' => [],
            'resultat' => '',
            'tri' => '',
            'note' => '',
        ];
    }

    /**
     * @return Vue
     */
    private static function bibliotheque(string $onglet): array
    {
        $total = self::total();
        $lignes = [];

        foreach (self::MEDIAS as [$nom, $fiche, $type, $poids, $droits, $diffusable]) {
            $fond = self::fondDroit($droits);
            $telechargement = self::telechargement($droits);

            $lignes[] = ['cellules' => [
                self::duo($nom, $diffusable ? 'Diffusable' : 'Non diffusable'),
                self::texte($fiche, discret: true),
                self::texte($type),
                self::texte($poids, gras: true, aligne: 'text-right'),
                self::jeton($droits, $fond),
                self::jeton($telechargement[0], $telechargement[1], aligne: 'text-right'),
            ]];
        }

        return array_merge(self::base($onglet, 'Bibliothèque de médias',
            self::nombre($total) . ' médias · 1,61 To sur 2 To. Un média appartient à une fiche, '
                . 'porte un droit d\'usage et se décline par canal — les trois conditionnent sa '
                . 'diffusion.'), [
            'indicateurs' => [
                self::indicateur('Médias', self::nombre($total), 'toutes fiches', 'images', 'bg-neutral-200'),
                self::indicateur('Stockage', '1,61 To', '81 % de 2 To', 'upload-simple', 'bg-peach-pastel'),
                self::indicateur('Sans droits déclarés', self::nombre(self::DROITS[3][1]),
                    'non diffusables', 'lock', 'bg-error-pastel'),
                self::indicateur('Doublons probables', (string) self::enDouble(),
                    'à dédoublonner', 'warning', 'bg-peach-pastel'),
            ],
            'colonnes' => [
                self::colonne('Média'), self::colonne('Fiche'), self::colonne('Type'),
                self::colonne('Poids', 'text-right'), self::colonne("Droits d'usage"),
                self::colonne('Télécharger', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => 'Affichage 1 – ' . \count(self::MEDIAS) . ' sur ' . self::nombre($total),
            'resultat' => self::nombre($total) . ' médias',
            'filtres' => [['Catégorie', 'Toutes'], ['Droits', 'Tous'], ['Type', 'Tous'], ['Canal', 'Tous']],
            'tri' => 'Import le plus récent',
            'note' => 'Le téléchargement propose les ' . \count(self::FORMATS) . ' déclinaisons — de '
                . self::FORMATS[0][1] . ' à ' . self::FORMATS[\count(self::FORMATS) - 1][1] . '. Les '
                . self::nombre(self::DROITS[2][1]) . ' médias en usage interne et les '
                . self::nombre(self::DROITS[3][1]) . ' sans droits déclarés en sont exclus.',
            'actions' => [
                ['Importer des médias', 'upload-simple', false],
                ['Classer par lot', 'rocket', true],
            ],
        ]);
    }

    /**
     * @return Vue
     */
    private static function import(string $onglet): array
    {
        $retouches = self::retouches();
        $lignes = [];

        foreach (self::RETOUCHE as [$nom, $quoi, $compte]) {
            $lignes[] = ['cellules' => [
                self::texte($nom, gras: true),
                self::phrase($quoi),
                self::texte(self::nombre($compte), gras: true, aligne: 'text-right'),
                self::jeton('Non destructif', 'bg-success-pastel'),
                self::lien('Ouvrir'),
            ]];
        }

        return array_merge(self::base($onglet, 'Import & retouche',
            'Le glisser-déposer accepte images, PDF, vidéos et bureautique. Toute retouche est non '
                . "destructive : l'original est conservé, les déclinaisons sont régénérées."), [
            'cartes' => [
                self::carte('Glisser-déposer',
                    "Déposez vos fichiers n'importe où sur cette page. L'IA classe, tague et détecte "
                        . 'les doublons à l\'import — vous validez ensuite.',
                    'Déposer', 'upload-simple', 'Ou parcourir vos fichiers', false),
                self::carte(self::nombre($retouches) . ' médias retouchés ce mois',
                    "Recadrage, rotation, compression et filtres. L'original reste intact et chaque "
                        . 'retouche est réversible.',
                    self::nombre($retouches), 'rocket', 'Non destructif', false),
            ],
            'colonnes' => [
                self::colonne('Outil'), self::colonne("Ce qu'il fait"),
                self::colonne('Ce mois', 'text-right'), self::colonne('Mode'),
                self::colonne('', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::RETOUCHE) . ' outils de retouche · ' . self::nombre($retouches)
                . ' médias retouchés ce mois',
            'actions' => [['Importer des médias', 'upload-simple', true]],
        ]);
    }

    /**
     * @return Vue
     */
    private static function ia(string $onglet): array
    {
        $lignes = [];

        foreach (self::IA as [$nom, $compte, $precision, $quand]) {
            $lignes[] = ['cellules' => [
                self::texte($nom, gras: true),
                self::texte(self::nombre($compte), gras: true, aligne: 'text-right'),
                self::texte($precision, gras: true, aligne: 'text-right', teinte: 'primary-3'),
                self::jeton($quand, 'bg-primary-4'),
                self::lien('Régler'),
            ]];
        }

        return array_merge(self::base($onglet, 'Reconnaissance IA',
            "Ce que l'IA déduit d'un média à l'import : son type, ses tags, son texte, et s'il "
                . "existe déjà. Rien n'est écrit sans validation — les propositions se traitent en lot."), [
            'cartes' => [self::carte('41 320 propositions en attente',
                "Classifications et tags proposés que personne n'a encore validés. Un traitement en "
                    . 'lot les absorbe, avec la confiance comme filtre.',
                '41 320', 'rocket', 'Valider par lot', true)],
            'colonnes' => [
                self::colonne('Traitement'), self::colonne('Médias traités', 'text-right'),
                self::colonne('Précision', 'text-right'), self::colonne('Déclenchement'),
                self::colonne('', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::IA) . ' traitements automatiques · aucune écriture sans validation humaine',
            'actions' => [['Valider par lot', 'rocket', true]],
        ]);
    }

    /**
     * @return Vue
     */
    private static function metadonnees(string $onglet): array
    {
        $lignes = [];
        $medias = 0;

        foreach (self::TYPES as [$nom, $extensions, $compte, $quoi]) {
            $medias += $compte;
            $lignes[] = ['cellules' => [
                self::texte($nom, gras: true),
                self::texte($extensions, discret: true),
                self::texte(self::nombre($compte), gras: true, aligne: 'text-right'),
                self::phrase($quoi),
            ]];
        }

        return array_merge(self::base($onglet, 'Métadonnées & types de fichiers',
            'Chaque média porte sa source, ses droits, sa validité, ses tags et ses mots-clés. Le '
                . 'type de fichier décide de ce que le DAM sait en faire.'), [
            'cartes' => [self::carte("Métadonnées d'un média",
                'Source · droits d\'usage · date de validité · tags automatiques · mots-clés de '
                    . 'recherche. Les cinq sont indexés : une recherche « terrasse été » retrouve '
                    . 'les visuels tagués ainsi.',
                '5 champs', 'note', 'Voir un média', false)],
            'colonnes' => [
                self::colonne('Type'), self::colonne('Extensions'),
                self::colonne('Médias', 'text-right'), self::colonne('Ce que le DAM en fait'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::TYPES) . ' types pris en charge · ' . self::nombre($medias) . ' médias',
            'actions' => [['Régler les métadonnées', 'arrows-out-cardinal', false]],
        ]);
    }

    /**
     * @return Vue
     */
    private static function formats(string $onglet): array
    {
        $lignes = [];

        foreach (self::FORMATS as [$nom, $dimensions, $usage, $poids]) {
            $lignes[] = ['cellules' => [
                self::texte($nom, gras: true),
                self::texte($dimensions, discret: true),
                self::texte($usage),
                self::texte($poids, gras: true, aligne: 'text-right'),
                self::lien('URL CDN'),
            ]];
        }

        $manquantes = self::nombre(self::DECLINAISONS_MANQUANTES);

        return array_merge(self::base($onglet, 'Formats & CDN',
            'Cinq formats générés à l\'import, servis par CDN avec URL optimisée et cache long. Un '
                . 'canal demande un format, pas un fichier — les déclinaisons manquantes bloquent '
                . 'ce canal seul.'), [
            'cartes' => [
                self::carte('URLs optimisées',
                    "Chaque format a son URL CDN avec cache d'un an et conversion WebP à la volée "
                        . 'selon le navigateur. Aucun canal ne sert l\'original.',
                    'CDN', 'arrow-right', 'cdn.businessprofilers.fr', false),
                self::carte($manquantes . ' déclinaisons manquantes',
                    'Kactus et Bedouk attendent des formats que ' . $manquantes . " médias n'ont pas. "
                        . 'La génération est un traitement de fond réversible.',
                    $manquantes, 'warning', 'Générer les manquantes', true),
            ],
            'colonnes' => [
                self::colonne('Format'), self::colonne('Dimensions'), self::colonne('Usage'),
                self::colonne('Poids moyen', 'text-right'), self::colonne('', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::FORMATS) . ' formats par média · ' . self::CANAUX . ' canaux servis · '
                . $manquantes . ' déclinaisons à générer',
            'actions' => [['Générer les manquantes', 'rocket', true]],
        ]);
    }

    /**
     * @return Vue
     */
    private static function droits(string $onglet): array
    {
        $total = self::total();
        $bloques = self::DROITS[3][1];
        $lignes = [];
        $axes = [];

        foreach (self::DROITS as [$nom, $compte, $fond, $autorise]) {
            $part = self::part($compte, $total);

            /*
             * L'axe ne porte plus que le nom du régime : son explication est
             * déjà la colonne « Ce qu'il autorise » du tableau, deux lignes
             * plus bas. Répétée ici, elle faisait déborder chaque axe sur deux
             * lignes et noyait le chiffre.
             */
            $axes[] = [
                'libelle' => $nom,
                'part' => $part,
                'poids' => self::nombre($compte) . ' médias',
                'fond' => $fond,
            ];

            $lignes[] = ['cellules' => [
                self::jeton($nom, $fond),
                self::texte(self::nombre($compte), gras: true, aligne: 'text-right'),
                self::barre($part),
                self::phrase($autorise),
            ]];
        }

        $consentements = [];

        foreach (self::CONSENTEMENTS as [, $fiche, $portee, $preuve]) {
            $consentements[] = ['libelle' => $fiche . ' · ' . $preuve, 'poids' => $portee];
        }

        return array_merge(self::base($onglet, "Droits d'usage & consentement",
            'Un média sans droits déclarés ne part sur aucun canal tiers, même si la fiche est '
                . 'publiée. Chaque consentement de visuel libre de droits est historisé avec sa preuve.'), [
            'sante' => [
                'titre' => 'Répartition des droits',
                'titreFaibles' => 'Consentements historisés',
                'score' => self::part(self::diffusables(), $total),
                'ligne' => self::nombre($total) . ' médias · ' . self::nombre(self::diffusables())
                    . ' diffusables sur canaux tiers',
                'axes' => $axes,
                'faibles' => $consentements,
            ],
            'cartes' => [
                self::carte(self::nombre($bloques) . ' médias bloqués',
                    "Sans droits déclarés, ils ne partent sur aucun canal tiers. Une déclaration par "
                        . 'lot les débloque, une demande au prestataire résout les cas douteux.',
                    self::nombre($bloques), 'lock', 'Déclarer par lot', true),
                self::carte('Consentement historisé',
                    'Chaque cession de droits est conservée avec sa preuve — cession signée ou accord '
                        . "par email — et sa date. L'historique est opposable.",
                    (string) \count(self::CONSENTEMENTS), 'note', "Voir l'historique", false),
            ],
            'colonnes' => [
                self::colonne("Droit d'usage"), self::colonne('Médias', 'text-right'),
                self::colonne('Part du stock'), self::colonne("Ce qu'il autorise"),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::DROITS) . ' régimes de droits · ' . self::nombre($bloques)
                . ' médias bloqués · ' . \count(self::CONSENTEMENTS) . ' consentements historisés',
            'actions' => [['Déclarer par lot', 'lock', true]],
        ]);
    }

    /**
     * @return Vue
     */
    private static function doublons(string $onglet): array
    {
        $enDouble = self::enDouble();
        $lignes = [];

        foreach (self::DOUBLONS as [$nom, $copies, $similarite, $decision]) {
            // « Vérifier à la main » n'est pas une décision : la teinte le dit.
            $certain = !str_starts_with($decision, 'Vérifier');

            $lignes[] = ['cellules' => [
                self::texte($nom, gras: true),
                self::texte((string) $copies, gras: true, aligne: 'text-right'),
                self::texte($similarite, gras: true, aligne: 'text-right',
                    teinte: $certain ? 'primary-3' : 'neutral-500'),
                self::jeton($decision, $certain ? 'bg-success-pastel' : 'bg-peach-pastel'),
                self::lien('Comparer'),
            ]];
        }

        return array_merge(self::base($onglet, 'Doublons détectés',
            $enDouble . ' médias en double sur ' . \count(self::DOUBLONS) . ' groupes. La détection '
                . 'compare les images elles-mêmes, pas les noms de fichier — un même visuel réimporté '
                . 'sous un autre nom est repéré.'), [
            'cartes' => [self::carte('Gain de stockage',
                'Dédoublonner les ' . \count(self::DOUBLONS) . ' groupes libère 1,8 Go et retire '
                    . $enDouble . " médias du catalogue. L'opération est réversible 30 jours.",
                '1,8 Go', 'rocket', 'Dédoublonner', false)],
            'colonnes' => [
                self::colonne('Média'), self::colonne('Copies', 'text-right'),
                self::colonne('Similarité', 'text-right'), self::colonne('Décision proposée'),
                self::colonne('', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::DOUBLONS) . ' groupes · ' . $enDouble
                . ' médias en double · 1,8 Go récupérables',
            'actions' => [['Dédoublonner', 'rocket', true]],
        ]);
    }

    /**
     * @return Vue
     */
    private static function synchronisation(string $onglet): array
    {
        $lignes = [];

        foreach (self::SYNCHRONISATIONS as [$sens, $quoi, $volume, $quand, $etat]) {
            $ajour = 'à jour' === $etat;

            $lignes[] = ['cellules' => [
                self::texte($sens, gras: true),
                self::texte($quoi, discret: true),
                self::texte(self::nombre($volume), gras: true, aligne: 'text-right'),
                self::texte($quand, discret: true),
                self::jeton($ajour ? 'À jour' : $etat, $ajour ? 'bg-success-pastel' : 'bg-peach-pastel'),
            ]];
        }

        return array_merge(self::base($onglet, 'Synchronisation DAM ↔ PIM',
            'Le DAM détient les fichiers, le PIM les rattache aux fiches. Les deux sens circulent : '
                . 'un média rattaché ici apparaît sur la fiche, une fiche supprimée libère ses médias.'), [
            'cartes' => [self::carte('Miroir DAM ↔ PIM',
                'Les deux référentiels concordent sur ' . self::nombre(self::total()) . ' médias. La '
                    . 'synchronisation nocturne propage les rattachements et les suppressions dans '
                    . 'les deux sens.',
                '100 %', 'ok-circle', 'Voir le journal', false)],
            'colonnes' => [
                self::colonne('Sens'), self::colonne('Ce qui circule'),
                self::colonne('Volume', 'text-right'), self::colonne('Dernier passage'),
                self::colonne('État'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::SYNCHRONISATIONS) . ' flux · synchronisation nocturne à 02:00 · '
                . self::nombre(self::DECLINAISONS_MANQUANTES) . ' déclinaisons en attente',
            'actions' => [
                ['Relancer la synchronisation', 'spinner', false],
                ['Voir le journal', 'note', true],
            ],
        ]);
    }

    /**
     * Ce que la colonne « Télécharger » propose selon le régime de droits.
     *
     * « Usage interne » et « Sans droits déclarés » ne sortent pas du DAM : la
     * cellule dit pourquoi plutôt que de proposer une action impossible.
     *
     * @return array{string, string}
     */
    private static function telechargement(string $droit): array
    {
        if ('Usage interne' === $droit) {
            return ['Interne seulement', 'bg-neutral-200'];
        }

        if (\in_array($droit, ['Libre de droits', 'Crédit obligatoire'], true)) {
            return ['5 formats', 'bg-primary-4'];
        }

        return ['Droits requis', 'bg-error-pastel'];
    }

    private static function fondDroit(string $droit): string
    {
        foreach (self::DROITS as [$nom, , $fond]) {
            if ($nom === $droit) {
                return $fond;
            }
        }

        return 'bg-error-pastel';
    }

    /**
     * @return Carte
     */
    private static function carte(
        string $titre,
        string $texte,
        string $valeur,
        string $icone,
        string $lien,
        bool $alerte,
    ): array {
        return [
            'titre' => $titre, 'texte' => $texte, 'valeur' => $valeur,
            'icone' => $icone, 'lien' => $lien, 'alerte' => $alerte,
        ];
    }

    /**
     * @return Indicateur
     */
    private static function indicateur(
        string $libelle,
        string $valeur,
        string $note,
        string $icone,
        string $fond,
    ): array {
        return [
            'libelle' => $libelle, 'valeur' => $valeur, 'note' => $note,
            'icone' => $icone, 'fond' => $fond,
        ];
    }

    /**
     * @return Colonne
     */
    private static function colonne(string $libelle, string $aligne = 'text-left'): array
    {
        return ['libelle' => $libelle, 'aligne' => $aligne];
    }

    /**
     * @return Cellule
     */
    private static function texte(
        string $texte,
        bool $gras = false,
        bool $discret = false,
        string $aligne = 'text-left',
        string $teinte = '',
    ): array {
        return self::cellule('texte', $texte, gras: $gras, discret: $discret,
            aligne: $aligne, teinte: $teinte);
    }

    /**
     * Texte qui s'enroule au lieu d'être tronqué : une phrase, pas un libellé.
     *
     * @return Cellule
     */
    private static function phrase(string $texte): array
    {
        return self::cellule('phrase', $texte, discret: true);
    }

    /**
     * @return Cellule
     */
    private static function duo(string $texte, string $note): array
    {
        return self::cellule('duo', $texte, note: $note, gras: true);
    }

    /**
     * @return Cellule
     */
    private static function jeton(string $texte, string $fond, string $aligne = 'text-left'): array
    {
        return self::cellule('jeton', $texte, gras: true, aligne: $aligne, fond: $fond,
            teinte: 'bg-neutral-200' === $fond ? 'neutral-500' : 'primary-3');
    }

    /**
     * @return Cellule
     */
    private static function lien(string $texte): array
    {
        return self::cellule('lien', $texte, gras: true, aligne: 'text-right');
    }

    /**
     * @return Cellule
     */
    private static function barre(int $part): array
    {
        return self::cellule('barre', $part . ' %', part: $part);
    }

    /**
     * @return Cellule
     */
    private static function cellule(
        string $type,
        string $texte,
        string $note = '',
        bool $gras = false,
        bool $discret = false,
        string $aligne = 'text-left',
        string $teinte = '',
        string $fond = '',
        int $part = 0,
    ): array {
        return [
            'type' => $type, 'texte' => $texte, 'note' => $note, 'gras' => $gras,
            'discret' => $discret, 'aligne' => $aligne, 'teinte' => $teinte,
            'fond' => $fond, 'part' => $part,
        ];
    }

    /** Part en pourcentage, garde contre un diviseur nul. */
    private static function part(int $compte, int $total): int
    {
        return 0 !== $total ? (int) round($compte / $total * 100) : 0;
    }

    /** Groupement par milliers à l'espace fine, comme partout dans le back-office. */
    private static function nombre(int $valeur): string
    {
        return number_format($valeur, 0, ',', "\u{202F}");
    }
}
