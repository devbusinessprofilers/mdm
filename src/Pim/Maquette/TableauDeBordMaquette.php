<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de l'écran « Tableau de bord d'accueil ».
 *
 * Maquette : `Tableau de bord.dc.html`. Les constantes sont celles du handoff,
 * reprises telles quelles ; tout le reste — sévérités, totaux, complétude
 * pondérée — est **dérivé**, comme dans la maquette, pour qu'un chiffre de tête
 * ne puisse jamais contredire le tableau qu'il résume.
 *
 * C'est du contenu de démonstration : il disparaît dès qu'un service métier
 * alimente l'écran.
 *
 * @phpstan-type Etat array{cle: string, libelle: string, note: string, actif: bool}
 * @phpstan-type File array{libelle: string, indice: string, icone: string, compte: string,
 *     brut: int, severite: string, etiquette: string}
 * @phpstan-type Zone array{cle: string, numero: string, titre: string, sousTitre: string,
 *     icone: string, badge: string, resume: string, avecResume: bool, actif: bool}
 * @phpstan-type Cellule array{libelle: string, palier: string}
 * @phpstan-type LigneCroisement array{nom: string, cellules: list<Cellule>, total: Cellule, totaux: bool}
 * @phpstan-type ChampFaible array{libelle: string, taux: string, compte: string}
 * @phpstan-type LigneUtilisateur array{nom: string, creees: string, enrichies: string,
 *     validees: string, part: string, partBrute: int}
 * @phpstan-type Publication array{nom: string, meta: string, quand: string}
 * @phpstan-type IndicateurEquipe array{libelle: string, valeur: string, delta: string,
 *     icone: string, teinte: string}
 * @phpstan-type Segment array{libelle: string, part: int, teinte: string}
 * @phpstan-type Vue array{etat: string, chargement: bool, parametrage: bool, vide: bool,
 *     salutation: string, sousTitre: string, periode: string, periodes: list<string>,
 *     files: list<File>, fileTotal: string, zones: list<Zone>, croisementMode: string,
 *     croisementSousTitre: string, croisementPays: list<string>,
 *     croisementLignes: list<LigneCroisement>, completudeGlobale: int,
 *     fichesTotal: string, publieesTotal: string, publieesPart: int,
 *     champsFaibles: list<ChampFaible>, utilisateurs: list<LigneUtilisateur>,
 *     indicateursEquipe: list<IndicateurEquipe>, publications: list<Publication>,
 *     segments: list<Segment>, stockageNote: string, mediasTotal: string}
 */
final class TableauDeBordMaquette
{
    /** Nombre total de fiches au référentiel, socle de tous les ratios. */
    private const FICHES_TOTAL = 18953;

    /** Seuils de sévérité, exprimés en multiples du volume normal d'une file. */
    private const SEUIL_ATTENTION = 1.5;
    private const SEUIL_CRITIQUE = 3.0;

    public const ETAT_PAR_DEFAUT = 'nominal';

    /** @var array<string, array{string, string}> Libellé et note de chaque état de la maquette */
    public const ETATS = [
        'nominal' => ['Vue nominale', "Du travail en attente en zone 1"],
        'vide' => ['Zone 1 vide', 'Tout est traité'],
        'fort' => ["Volume d'alertes élevé", 'Plusieurs centaines en attente'],
        'chargement' => ['Chargement progressif', "Les zones arrivent l'une après l'autre"],
        'param' => ['Mode paramétrage', 'Blocs réorganisables et masquables'],
    ];

    /** Rang du jeu de compteurs à lire dans les files, selon l'état. */
    private const COLONNE_ETAT = ['nominal' => 0, 'vide' => 1, 'fort' => 2];

    /**
     * Les six files de travail — pas des statistiques.
     *
     * Chacune porte son volume quotidien normal : trois traitements en échec,
     * c'est grave ; cent cinquante fiches en attente de publication, c'est un
     * mardi. La sévérité se lit en ratio à ce volume, jamais en absolu.
     * `traitement` reprend le langage du socle : un traitement échoué est un
     * état, pas un volume — toute occurrence est donc critique.
     *
     * Libellé, indice, glyphe, compteurs par état, volume normal, nature.
     *
     * @var list<array{string, string, string, array{int, int, int}, int, string}>
     */
    private const FILES = [
        ['Demandes de référencement', 'Salesforce', 'note', [14, 0, 19], 12, 'volume'],
        ['Fiches à valider', 'Prêtes pour relecture', 'ok-circle', [47, 0, 341], 60, 'volume'],
        ['Fiches à publier', 'Validées, non diffusées', 'eye', [23, 0, 51], 40, 'volume'],
        ['Accès non transmis', 'Extranet prestataire', 'lock', [9, 0, 12], 10, 'volume'],
        ['Anomalies de gouvernance', 'Écarts entre systèmes', 'warning', [31, 0, 25], 15, 'volume'],
        ['Traitements en échec', 'Imports, exports, IA', 'spinner', [2, 0, 5], 0, 'traitement'],
    ];

    /** @var list<string> */
    public const CROISEMENT_PAYS = ['France', 'Belgique', 'Suisse', 'Espagne', 'Italie', 'R.-U.'];

    /**
     * Typologie, taux de complétude par pays, fiches publiées par pays.
     *
     * @var list<array{string, list<int>, list<int>}>
     */
    private const CROISEMENT_TYPOLOGIES = [
        ['Lieux', [78, 71, 74, 62, 58, 66], [7945, 520, 410, 330, 230, 170]],
        ['Restaurants', [72, 68, 70, 59, 61, 64], [1980, 190, 150, 125, 100, 85]],
        ['Activités', [64, 58, 61, 52, 49, 55], [960, 115, 90, 75, 55, 45]],
        ['Prestataires', [69, 63, 66, 57, 54, 60], [1420, 145, 110, 88, 72, 58]],
        ['Plateaux repas', [81, 74, 76, 68, 64, 71], [340, 30, 24, 20, 14, 10]],
    ];

    /** @var list<array{string, int, string}> */
    private const CHAMPS_FAIBLES = [
        ['Descriptif des salles de séminaires', 18, '15 402 fiches'],
        ['Coordonnées GPS', 24, '14 405 fiches'],
        ['Capacité en configuration classe', 31, '13 077 fiches'],
        ['Certifications RSE', 36, '12 130 fiches'],
        ['Tarif basse saison', 42, '10 993 fiches'],
    ];

    /** Nom, fiches créées, enrichies, validées. @var list<array{string, int, int, int}> */
    private const UTILISATEURS = [
        ['M. Rousseau', 12, 186, 42],
        ['C. Berthier', 9, 154, 31],
        ['L. Garnier', 7, 132, 24],
        ['A. Dufour', 4, 98, 18],
        ['S. Moreau', 3, 72, 11],
    ];

    /** @var list<array{string, string, string}> */
    private const PUBLICATIONS = [
        ['Domaine de Chantilly', 'Lieux · France', 'il y a 12 min'],
        ['Restaurant Villa M', 'Restaurants · France', 'il y a 38 min'],
        ['Karting Wembley', 'Activités · R.-U.', 'il y a 1 h'],
        ['Traiteur Lenôtre Events', 'Prestataires · France', 'il y a 2 h'],
        ['Pavillon Dauphine', 'Lieux · France', 'il y a 3 h'],
    ];

    /** Libellé, valeur, delta, glyphe, teinte. @var list<array{string, string, string, string, string}> */
    private const INDICATEURS_EQUIPE = [
        ['Temps moyen de validation', '1 j 4 h', '−6 h', 'ok-circle', 'text-success'],
        ['Mises à jour cette semaine', '4 218', '+12 %', 'pencil', 'text-primary'],
        ['Champs modifiés ou enrichis', '9 640', '+8 %', 'rocket', 'text-primary-3'],
        ['Nouvelles fiches cette semaine', '186', '+21 %', 'plus-circle', 'text-primary'],
    ];

    /** Libellé, part en pourcentage, teinte. @var list<array{string, int, string}> */
    private const SEGMENTS_STOCKAGE = [
        ['Photos · 1,12 To', 62, 'bg-primary'],
        ['Plans et PDF · 324 Go', 18, 'bg-primary-4'],
        ['Vidéos · 162 Go', 9, 'bg-primary-3'],
    ];

    /** @var list<string> */
    public const PERIODES = ['7 jours', '30 jours', 'Trimestre', 'Année'];

    public const PERIODE_PAR_DEFAUT = '30 jours';

    /** Clé, numéro, titre, sous-titre, glyphe. @var list<array{string, string, string, string, string}> */
    private const ZONES = [
        ['files', '1', 'À traiter', 'Vos files de travail du jour', 'warning'],
        ['sante', '2', 'Santé du référentiel', "Ce que vaut la donnée aujourd'hui", 'ok-circle'],
        ['activite', '3', 'Activité des équipes', 'Qui produit quoi, et à quel rythme', 'users'],
        ['medias', '4', 'Médias et stockage', 'Volume et consommation', 'images'],
    ];

    private const STOCKAGE_UTILISE = '1,61 To';

    public static function etatValide(string $etat): string
    {
        return \array_key_exists($etat, self::ETATS) ? $etat : self::ETAT_PAR_DEFAUT;
    }

    public static function periodeValide(string $periode): string
    {
        return \in_array($periode, self::PERIODES, true) ? $periode : self::PERIODE_PAR_DEFAUT;
    }

    /**
     * @return Vue
     */
    public static function vue(string $etat, string $periode, string $croisementMode, int $zoneActive): array
    {
        $etat = self::etatValide($etat);
        $periode = self::periodeValide($periode);
        $parPourcentage = 'publiees' !== $croisementMode;

        $chargement = 'chargement' === $etat;
        $parametrage = 'param' === $etat;
        $vide = 'vide' === $etat;

        $colonne = self::COLONNE_ETAT[$etat] ?? 0;
        $files = self::files($colonne);
        $fileTotal = array_sum(array_column($files, 'brut'));

        $publiees = self::publieesTotal();
        $completude = self::completudeGlobale();

        return [
            'etat' => $etat,
            'chargement' => $chargement,
            'parametrage' => $parametrage,
            'vide' => $vide,
            'salutation' => 'Bonjour Marie',
            'sousTitre' => self::sousTitre($etat, $files, $fileTotal),
            'periode' => $periode,
            'periodes' => self::PERIODES,
            'files' => $files,
            'fileTotal' => self::nombre($fileTotal),
            'zones' => self::zones($zoneActive, $vide, $fileTotal, $completude, $periode),
            'croisementMode' => $parPourcentage ? 'completude' : 'publiees',
            'croisementSousTitre' => $parPourcentage
                ? 'Taux de complétude moyen par couple'
                : 'Fiches publiées par couple',
            'croisementPays' => self::CROISEMENT_PAYS,
            'croisementLignes' => self::croisement($parPourcentage, $completude),
            'completudeGlobale' => $completude,
            'fichesTotal' => self::nombre(self::FICHES_TOTAL),
            'publieesTotal' => self::nombre($publiees),
            'publieesPart' => (int) round($publiees / self::FICHES_TOTAL * 100),
            'champsFaibles' => self::champsFaibles(),
            'utilisateurs' => self::utilisateurs(),
            'indicateursEquipe' => self::indicateursEquipe(),
            'publications' => self::publications(),
            'segments' => self::segments(),
            'stockageNote' => self::STOCKAGE_UTILISE . ' utilisés sur 2 To · 81 %',
            'mediasTotal' => '142 806',
        ];
    }

    /**
     * @return list<Etat>
     */
    public static function etats(string $actif): array
    {
        $etats = [];

        foreach (self::ETATS as $cle => [$libelle, $note]) {
            $etats[] = ['cle' => $cle, 'libelle' => $libelle, 'note' => $note, 'actif' => $cle === $actif];
        }

        return $etats;
    }

    /**
     * Les six files, avec leur sévérité dérivée.
     *
     * @return list<File>
     */
    private static function files(int $colonne): array
    {
        $files = [];

        foreach (self::FILES as [$libelle, $indice, $icone, $comptes, $normal, $nature]) {
            $brut = $comptes[$colonne];
            [$severite, $ratio] = self::severite($brut, $normal, $nature);

            $files[] = [
                'libelle' => $libelle,
                'indice' => $indice,
                'icone' => $icone,
                'compte' => self::nombre($brut),
                'brut' => $brut,
                'severite' => $severite,
                'etiquette' => 'traitement' === $nature
                    ? 'Échoué'
                    : '×' . str_replace('.', ',', (string) (round($ratio * 10) / 10)) . ' le volume normal',
            ];
        }

        return $files;
    }

    /**
     * @return array{string, float} niveau (`none`, `warn`, `hot`) et ratio au volume normal
     */
    private static function severite(int $compte, int $normal, string $nature): array
    {
        if (0 === $compte) {
            return ['none', 0.0];
        }

        if ('traitement' === $nature) {
            return ['hot', 0.0];
        }

        $ratio = 0 !== $normal ? $compte / $normal : 0.0;

        if ($ratio >= self::SEUIL_CRITIQUE) {
            return ['hot', $ratio];
        }

        return [$ratio >= self::SEUIL_ATTENTION ? 'warn' : 'none', $ratio];
    }

    /**
     * @param list<File> $files
     */
    private static function sousTitre(string $etat, array $files, int $total): string
    {
        if ('chargement' === $etat) {
            return 'Relevé des files en cours…';
        }

        if ('vide' === $etat) {
            return 'Aucune file en attente — le référentiel est à jour.';
        }

        $chaudes = \count(array_filter($files, static fn (array $f): bool => 'hot' === $f['severite']));
        $tiedes = \count(array_filter($files, static fn (array $f): bool => 'warn' === $f['severite']));

        $verdict = match (true) {
            $chaudes > 1 => $chaudes . ' files hors norme à lever d\'abord.',
            1 === $chaudes => '1 file hors norme à lever d\'abord.',
            $tiedes > 1 => $tiedes . ' files au-dessus de leur volume normal.',
            1 === $tiedes => '1 file au-dessus de son volume normal.',
            default => 'Toutes les files sont dans leur volume normal.',
        };

        return self::nombre($total) . ' éléments à traiter, répartis sur 6 files. ' . $verdict;
    }

    /**
     * @return list<Zone>
     */
    private static function zones(int $actif, bool $vide, int $fileTotal, int $completude, string $periode): array
    {
        $zones = [];

        foreach (self::ZONES as $rang => [$cle, $numero, $titre, $sousTitre, $icone]) {
            $zones[] = [
                'cle' => $cle,
                'numero' => $numero,
                'titre' => $titre,
                'sousTitre' => $sousTitre,
                'icone' => $icone,
                'badge' => match ($cle) {
                    'files' => self::nombre($fileTotal),
                    'sante' => $completude . ' %',
                    'activite' => '5',
                    default => self::STOCKAGE_UTILISE,
                },
                'resume' => match ($cle) {
                    'files' => $vide ? 'Aucune file active' : self::nombre($fileTotal) . ' éléments en attente',
                    'sante' => '18 953 fiches · 6 pays',
                    default => '5 utilisateurs actifs · ' . mb_strtolower($periode),
                },
                'avecResume' => 'medias' !== $cle,
                'actif' => $rang === $actif,
            ];
        }

        return $zones;
    }

    /** Fiches publiées, tous pays et toutes typologies. */
    private static function publieesTotal(): int
    {
        $total = 0;

        foreach (self::CROISEMENT_TYPOLOGIES as [, , $comptes]) {
            $total += array_sum($comptes);
        }

        return $total;
    }

    /**
     * Complétude globale, pondérée par le nombre de fiches — pas une moyenne de
     * moyennes. C'est la seule dérivation : l'indicateur, la barre, le badge du
     * rail et le total du tableau la lisent tous.
     */
    private static function completudeGlobale(): int
    {
        $numerateur = 0;
        $denominateur = 0;

        foreach (self::CROISEMENT_TYPOLOGIES as [, $taux, $comptes]) {
            foreach ($taux as $rang => $pourcentage) {
                $numerateur += $pourcentage * $comptes[$rang];
                $denominateur += $comptes[$rang];
            }
        }

        return 0 !== $denominateur ? (int) round($numerateur / $denominateur) : 0;
    }

    /**
     * Le tableau pays × typologie, avec sa ligne de totaux.
     *
     * @return list<LigneCroisement>
     */
    private static function croisement(bool $parPourcentage, int $completude): array
    {
        $lignes = [];

        foreach (self::CROISEMENT_TYPOLOGIES as [$nom, $taux, $comptes]) {
            $total = array_sum($comptes);
            $moyenne = self::moyennePonderee($taux, $comptes);

            $cellules = [];

            foreach ($taux as $rang => $pourcentage) {
                $cellules[] = [
                    'libelle' => $parPourcentage
                        ? $pourcentage . ' %'
                        : self::nombre($comptes[$rang]),
                    'palier' => self::palier($pourcentage),
                ];
            }

            $lignes[] = [
                'nom' => $nom,
                'cellules' => $cellules,
                'total' => [
                    'libelle' => $parPourcentage ? $moyenne . ' %' : self::nombre($total),
                    'palier' => 'total',
                ],
                'totaux' => false,
            ];
        }

        // La ligne et la colonne de totaux donnent la lecture à une dimension
        // sans dupliquer la mesure dans un bloc à part.
        $cellules = [];
        $grandTotal = 0;

        foreach (array_keys(self::CROISEMENT_PAYS) as $rang) {
            $tauxPays = [];
            $comptesPays = [];

            foreach (self::CROISEMENT_TYPOLOGIES as [, $taux, $comptes]) {
                $tauxPays[] = $taux[$rang];
                $comptesPays[] = $comptes[$rang];
            }

            $grandTotal += array_sum($comptesPays);
            $cellules[] = [
                'libelle' => $parPourcentage
                    ? self::moyennePonderee($tauxPays, $comptesPays) . ' %'
                    : self::nombre(array_sum($comptesPays)),
                'palier' => 'total',
            ];
        }

        $lignes[] = [
            'nom' => 'Tous pays',
            'cellules' => $cellules,
            'total' => [
                'libelle' => $parPourcentage ? $completude . ' %' : self::nombre($grandTotal),
                'palier' => 'grand',
            ],
            'totaux' => true,
        ];

        return $lignes;
    }

    /**
     * Moyenne de complétude pondérée par le nombre de fiches — jamais une
     * moyenne de moyennes. Sert aux totaux de ligne comme de colonne.
     *
     * @param list<int> $taux
     * @param list<int> $poids
     */
    private static function moyennePonderee(array $taux, array $poids): int
    {
        $denominateur = array_sum($poids);

        if (0 === $denominateur) {
            return 0;
        }

        $numerateur = 0;

        foreach ($taux as $rang => $pourcentage) {
            $numerateur += $pourcentage * $poids[$rang];
        }

        return (int) round($numerateur / $denominateur);
    }

    /**
     * Les jetons pâles sont des surfaces, jamais du texte : le signal du palier
     * reste au fond, le premier plan reste marine.
     */
    private static function palier(int $pourcentage): string
    {
        return match (true) {
            $pourcentage >= 75 => 'complet',
            $pourcentage >= 60 => 'publiable',
            default => 'insuffisant',
        };
    }

    /**
     * @return list<ChampFaible>
     */
    private static function champsFaibles(): array
    {
        return array_map(
            static fn (array $c): array => ['libelle' => $c[0], 'taux' => $c[1] . ' %', 'compte' => $c[2]],
            self::CHAMPS_FAIBLES,
        );
    }

    /**
     * La part se lit par rapport au plus actif, pas au total : c'est un
     * classement, pas une répartition.
     *
     * @return list<LigneUtilisateur>
     */
    private static function utilisateurs(): array
    {
        $maximum = self::UTILISATEURS[0][2];

        return array_map(
            static function (array $u) use ($maximum): array {
                $part = (int) round($u[2] / $maximum * 100);

                return [
                    'nom' => $u[0],
                    'creees' => (string) $u[1],
                    'enrichies' => (string) $u[2],
                    'validees' => (string) $u[3],
                    'part' => $part . ' %',
                    'partBrute' => $part,
                ];
            },
            self::UTILISATEURS,
        );
    }

    /**
     * @return list<IndicateurEquipe>
     */
    private static function indicateursEquipe(): array
    {
        return array_map(
            static fn (array $i): array => [
                'libelle' => $i[0], 'valeur' => $i[1], 'delta' => $i[2],
                'icone' => $i[3], 'teinte' => $i[4],
            ],
            self::INDICATEURS_EQUIPE,
        );
    }

    /**
     * @return list<Publication>
     */
    private static function publications(): array
    {
        return array_map(
            static fn (array $p): array => ['nom' => $p[0], 'meta' => $p[1], 'quand' => $p[2]],
            self::PUBLICATIONS,
        );
    }

    /**
     * @return list<Segment>
     */
    private static function segments(): array
    {
        return array_map(
            static fn (array $s): array => ['libelle' => $s[0], 'part' => $s[1], 'teinte' => $s[2]],
            self::SEGMENTS_STOCKAGE,
        );
    }

    /** Groupement par milliers à l'espace fine, comme partout dans le back-office. */
    private static function nombre(int $valeur): string
    {
        return number_format($valeur, 0, ',', "\u{202F}");
    }
}
