<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de l'écran « Qualité » — Data Governance Workspace.
 *
 * Maquette : `MDM prototype.dc.html`, page `qualite`.
 *
 * Le MDM ne note pas la donnée, il mesure le **miroir** entre Salesforce, le
 * MDM et le portail BP. Une donnée est saine quand les trois portent la même
 * valeur ; le reste est une anomalie à trancher. Cinq onglets, une question
 * chacun : d'où vient l'écart, lequel arbitrer, lequel normaliser, qui a été
 * prévenu, qui a tranché.
 *
 * Comme partout ailleurs, seules les constantes viennent du handoff : scores,
 * parts et totaux sont dérivés, pour qu'un chiffre de tête ne puisse pas
 * contredire le tableau qu'il résume.
 *
 * @phpstan-type Onglet array{cle: string, libelle: string, icone: string, badge: string, actif: bool}
 * @phpstan-type Cellule array{type: string, texte: string, gras: bool, discret: bool,
 *     aligne: string, teinte: string, fond: string, pastille: string}
 * @phpstan-type Ligne array{cellules: list<Cellule>}
 * @phpstan-type Colonne array{libelle: string, aligne: string}
 * @phpstan-type Carte array{titre: string, texte: string, valeur: string, icone: string,
 *     lien: string, alerte: bool}
 * @phpstan-type Axe array{libelle: string, part: int, poids: string, palier: string}
 * @phpstan-type Sante array{titre: string, titreFaibles: string, score: int, etiquette: string,
 *     etiquettePalier: string, ligne: string, axes: list<Axe>, faibles: list<Axe>}
 * @phpstan-type Vue array{onglet: string, titre: string, sousTitre: string,
 *     onglets: list<Onglet>, cartes: list<Carte>, colonnes: list<Colonne>, lignes: list<Ligne>,
 *     pied: string, actions: list<array{string, string, bool}>, sante: Sante|null,
 *     filtres: list<array{string, string}>, resultat: string, tri: string}
 */
final class QualiteMaquette
{
    public const ONGLET_PAR_DEFAUT = 'miroir';

    /** Seuil sous lequel le miroir déclenche une notification haute. */
    private const SEUIL = 90;

    /**
     * Les quatre états possibles d'un champ comparé entre les trois entités.
     *
     * Libellé, nombre de champs, raison.
     *
     * @var list<array{string, int, string}>
     */
    private const MIROIR = [
        ['Miroir parfait', 16842, 'les trois entités portent la même valeur'],
        ['Écart de forme', 1227, 'même valeur, casse ou format différents'],
        ['Conflit de valeur', 657, 'deux entités se contredisent, arbitrage requis'],
        ["Absent d'une entité", 227, 'la donnée manque dans une des trois'],
    ];

    /**
     * Comparatif champ par champ sur une fiche.
     *
     * Champ, Salesforce, MDM, portail BP, état, entité qui fait foi.
     *
     * @var list<array{string, string, string, string, string, string}>
     */
    private const COMPARATIF = [
        ['Raison sociale', 'SAS Domaine de Montvillargenne', 'SAS Domaine de Montvillargenne',
            'Château de Montvillargenne SAS', 'conflit', 'Salesforce'],
        ['SIRET', '38412764500019', '38412764500019', '38412764500019', 'ok', 'Salesforce'],
        ['Nom commercial', 'Château de Montvillargenne', 'Château de Montvillargenne',
            'Château de Montvillargenne', 'ok', 'MDM'],
        ['Adresse', '6 av. François Mathet', '6 av. François Mathet',
            '6 avenue François Mathet', 'forme', 'Salesforce'],
        ['Code postal', '60270', '60270', '60270', 'ok', 'Salesforce'],
        ['Téléphone', '+33 3 44 62 37 37', '+33 3 44 62 37 37', '03 44 62 36 00', 'conflit', 'Portail'],
        ['Email de contact', '—', 'c.berthier@montvillargenne.fr', 'c.berthier@montvillargenne.fr',
            'absent', 'Portail'],
        ['Nombre de chambres', '—', '120', '120', 'absent', 'MDM'],
        ['Nombre de salles', '—', '9', '27', 'conflit', 'MDM'],
        ['Capacité cocktail', '—', '480', '480', 'absent', 'MDM'],
    ];

    /**
     * Champ, entités en désaccord, fiches, autorité qui tranche, origine de l'écart.
     *
     * @var list<array{string, string, int, string, string}>
     */
    private const CONFLITS = [
        ['Raison sociale', 'Salesforce ≠ Portail', 84, 'Salesforce', 'Import SF du 2 août'],
        ['Téléphone', 'Portail ≠ Salesforce', 62, 'Portail', 'Saisie prestataire du 28 juillet'],
        ['Nombre de salles', 'MDM ≠ Portail', 47, 'MDM', 'Extraction PDF du 15 juillet'],
        ['Capacité cocktail', 'MDM ≠ Portail', 31, 'MDM', 'Saisie prestataire du 30 juillet'],
        ['Adresse de facturation', 'Salesforce ≠ MDM', 19, 'Salesforce', 'Import SF du 2 août'],
    ];

    /**
     * Champ, nature de l'écart, fiches, règle de normalisation.
     *
     * @var list<array{string, string, int, string}>
     */
    private const FORMES = [
        ['Adresse', '« av. » contre « avenue »', 1227, 'Développer les abréviations'],
        ['Téléphone', 'National contre international', 842, 'Format E.164'],
        ['Raison sociale', 'Casse mixte contre majuscules', 418, 'Casse du titre'],
        ['Code postal', 'Espace insécable parasite', 96, 'Retirer les espaces'],
    ];

    /**
     * Sévérité, message, quand, destinataire, état.
     *
     * @var list<array{string, string, string, string, string}>
     */
    private const NOTIFICATIONS = [
        ['haute', "84 nouveaux conflits de raison sociale après l'import Salesforce du 2 août",
            'il y a 6 h', 'Équipe Supply · M. Rousseau', 'ouverte'],
        ['haute', 'Le miroir est passé sous le seuil de 90 % — 89 % ce matin',
            'il y a 6 h', 'Data Governance · C. Philips', 'ouverte'],
        ['moyenne', '62 conflits de téléphone après une vague de saisies prestataires',
            'hier', 'Équipe Supply', 'ouverte'],
        ['basse', "1 227 écarts de forme sur l'adresse — normalisation proposée",
            'il y a 3 j', 'Data Governance', 'traitée'],
        ['moyenne', "Salesforce n'a pas répondu à la comparaison de 02:14, relance à 04:00",
            'il y a 4 j', 'Exploitation', 'traitée'],
    ];

    /**
     * Quand, qui, champ, décision, portée.
     *
     * @var list<array{string, string, string, string, string}>
     */
    private const DECISIONS = [
        ['3 août 09:12', 'M. Rousseau', 'Raison sociale', 'Salesforce fait foi', '84 fiches'],
        ['2 août 17:40', 'C. Berthier', 'Téléphone', 'Portail fait foi', '62 fiches'],
        ['31 juillet 11:05', 'C. Philips', 'Nombre de salles', 'MDM fait foi', '47 fiches'],
        ['28 juillet 15:22', 'M. Rousseau', 'Email de contact', 'Portail fait foi', '1 842 fiches'],
        ['24 juillet 10:08', 'A. Dufour', 'Adresse', 'Normalisation E.164', '842 fiches'],
    ];

    /** Valeur portée par une entité qui ne connaît pas le champ. */
    private const ABSENT = '—';

    /** Teinte de la pastille de chaque entité. @var array<string, string> */
    public const ENTITES = [
        'Salesforce' => 'bg-orange',
        'MDM' => 'bg-primary',
        'Portail' => 'bg-success',
    ];

    /** Libellé et fond de chaque état du miroir. @var array<string, array{string, string}> */
    private const ETATS = [
        'ok' => ['Miroir', 'bg-success-pastel'],
        'forme' => ['Forme', 'bg-primary-4'],
        'conflit' => ['Conflit', 'bg-error-pastel'],
        'absent' => ['Absent', 'bg-neutral-200'],
    ];

    /** Fond de chaque niveau de sévérité. @var array<string, array{string, string}> */
    private const SEVERITES = [
        'haute' => ['Haute', 'bg-error-pastel'],
        'moyenne' => ['Moyenne', 'bg-peach-pastel'],
        'basse' => ['Basse', 'bg-neutral-200'],
    ];

    public static function ongletValide(string $onglet): string
    {
        return \in_array($onglet, ['miroir', 'conflits', 'formes', 'notifs', 'decisions'], true)
            ? $onglet
            : self::ONGLET_PAR_DEFAUT;
    }

    /**
     * @return Vue
     */
    public static function vue(string $onglet): array
    {
        $onglet = self::ongletValide($onglet);

        return match ($onglet) {
            'conflits' => self::conflits($onglet),
            'formes' => self::formes($onglet),
            'notifs' => self::notifications($onglet),
            'decisions' => self::decisions($onglet),
            default => self::miroir($onglet),
        };
    }

    /** Total des champs comparés. */
    private static function total(): int
    {
        $total = 0;

        foreach (self::MIROIR as [, $compte]) {
            $total += $compte;
        }

        return $total;
    }

    /** Part des champs en miroir parfait — le score de la page. */
    public static function score(): int
    {
        return (int) round(self::MIROIR[0][1] / self::total() * 100);
    }

    /**
     * Les cinq onglets du poste de travail.
     *
     * @return list<Onglet>
     */
    public static function onglets(string $actif): array
    {
        $entrees = [
            ['miroir', 'Comparatif des 3 entités', 'users', self::score() . ' %'],
            ['conflits', 'Conflits à arbitrer', 'warning', self::nombre(self::MIROIR[2][1])],
            ['formes', 'Écarts de forme', 'note', self::nombre(self::MIROIR[1][1])],
            ['notifs', 'Notifications', 'info-circle', (string) self::ouvertes()],
            ['decisions', "Décisions d'arbitrage", 'ok-circle', (string) \count(self::DECISIONS)],
        ];

        $onglets = [];

        foreach ($entrees as [$cle, $libelle, $icone, $badge]) {
            $onglets[] = [
                'cle' => $cle,
                'libelle' => $libelle,
                'icone' => $icone,
                'badge' => $badge,
                'actif' => $cle === $actif,
            ];
        }

        return $onglets;
    }

    private static function ouvertes(): int
    {
        return \count(array_filter(
            self::NOTIFICATIONS,
            static fn (array $n): bool => 'ouverte' === $n[4],
        ));
    }

    /**
     * Bloc de santé partagé par l'onglet « miroir ».
     *
     * @return Sante
     */
    private static function sante(): array
    {
        $total = self::total();
        $score = self::score();
        $conflits = self::MIROIR[2][1];

        $axes = [];

        foreach (self::MIROIR as [$nom, $compte, $pourquoi]) {
            $part = self::part($compte, $total);
            $axes[] = [
                'libelle' => $nom . ' · ' . $pourquoi,
                'part' => $part,
                'poids' => self::nombre($compte) . ' champs',
                'palier' => self::palier($part),
            ];
        }

        /*
         * ÉCART : le handoff rapporte chaque conflit au total des champs
         * comparés (84 / 18 953), ce qui affiche « 0 % » sur les cinq lignes.
         * La part est ici calculée sur le volume de conflits, qui est le sujet
         * de la liste — sans quoi la colonne ne dit rien.
         */
        $faibles = [];

        foreach (self::CONFLITS as [$champ, $ecart, $compte]) {
            $faibles[] = [
                'libelle' => $champ . ' · ' . $ecart,
                'part' => self::part($compte, $conflits),
                'poids' => self::nombre($compte) . ' fiches',
                'palier' => 'insuffisant',
            ];
        }

        return [
            'titre' => 'Miroir entre les trois entités',
            'titreFaibles' => 'Anomalies par champ',
            'score' => $score,
            'etiquette' => match (true) {
                $score >= self::SEUIL => 'Conforme',
                $score >= self::SEUIL - 10 => 'Sous le seuil',
                default => 'Alerte',
            },
            'etiquettePalier' => match (true) {
                $score >= self::SEUIL => 'bg-success-pastel',
                $score >= self::SEUIL - 10 => 'bg-peach-pastel',
                default => 'bg-error-pastel',
            },
            'ligne' => self::nombre($total) . ' champs comparés · '
                . self::nombre(self::MIROIR[0][1]) . ' concordent',
            'axes' => $axes,
            'faibles' => $faibles,
        ];
    }

    /**
     * @return Vue
     */
    private static function miroir(string $onglet): array
    {
        $score = self::score();
        $total = self::total();
        $conflits = \count(array_filter(
            self::COMPARATIF,
            static fn (array $l): bool => 'conflit' === $l[4],
        ));
        $formes = \count(array_filter(
            self::COMPARATIF,
            static fn (array $l): bool => 'forme' === $l[4],
        ));

        $lignes = [];

        foreach (self::COMPARATIF as [$champ, $sf, $mdm, $portail, $etat, $foi]) {
            $lignes[] = ['cellules' => [
                self::texte($champ, gras: true),
                self::point($sf, 'Salesforce'),
                self::point($mdm, 'MDM', gras: true),
                self::point($portail, 'Portail'),
                self::jeton(self::ETATS[$etat][0], self::ETATS[$etat][1]),
                self::point($foi, $foi, discret: true),
            ]];
        }

        return [
            'onglet' => $onglet,
            'titre' => 'Gouvernance des données',
            'sousTitre' => "Le MDM ne note pas la donnée, il mesure le miroir entre Salesforce, "
                . "le MDM et le portail BP. Une donnée est saine quand les trois portent la même "
                . "valeur — le reste est une anomalie à trancher.",
            'onglets' => self::onglets($onglet),
            'sante' => self::sante(),
            'cartes' => [
                self::carte(
                    self::ouvertes() . ' notifications ouvertes',
                    'La comparaison de cette nuit a relevé 84 nouveaux conflits sur la raison '
                        . "sociale, tous consécutifs à un import Salesforce du 2 août.",
                    '84', 'warning', 'Voir les notifications', true,
                ),
                self::carte(
                    'Miroir parfait à ' . $score . ' %',
                    self::nombre(self::MIROIR[0][1]) . ' champs sur ' . self::nombre($total)
                        . " concordent entre les trois entités. Le seuil d'alerte est fixé à "
                        . self::SEUIL . ' %.',
                    $score . ' %',
                    $score >= self::SEUIL ? 'ok-circle' : 'warning',
                    'Seuil : ' . self::SEUIL . ' % · ' . ($score >= self::SEUIL ? 'atteint' : 'non atteint'),
                    $score < self::SEUIL,
                ),
                self::carte(
                    'Arbitrage en masse',
                    'Les 84 conflits de raison sociale portent le même écart : trancher une fois '
                        . 'pose la règle pour tout le lot.',
                    '84', 'rocket', 'Arbitrer les 84 fiches', false,
                ),
            ],
            'colonnes' => [
                self::colonne('Champ'), self::colonne('Salesforce'), self::colonne('MDM'),
                self::colonne('Portail BP'), self::colonne('État du miroir'), self::colonne('Fait foi'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::COMPARATIF) . ' champs comparés sur Château de Montvillargenne · '
                . $conflits . ' conflits, ' . $formes . ' écart de forme',
            'resultat' => 'Château de Montvillargenne',
            'filtres' => [
                ['Fiche', 'Château de Montvillargenne'],
                ['Comparé', 'cette nuit à 02:14'],
                ['Entités', 'Salesforce · MDM · Portail BP'],
            ],
            'tri' => "Conflits d'abord",
            'actions' => [
                ['Relancer la comparaison', 'spinner', false],
                ['Arbitrer les conflits', 'ok-circle', true],
            ],
        ];
    }

    /**
     * @return Vue
     */
    private static function conflits(string $onglet): array
    {
        $lignes = [];
        $fiches = 0;

        foreach (self::CONFLITS as [$champ, $ecart, $compte, $foi, $origine]) {
            $fiches += $compte;
            $lignes[] = ['cellules' => [
                self::texte($champ, gras: true),
                self::texte($ecart, discret: true),
                self::texte(self::nombre($compte), gras: true, aligne: 'text-right'),
                self::point($foi, $foi, discret: true),
                self::texte($origine, discret: true),
            ]];
        }

        $cartes = [];

        foreach (\array_slice(self::CONFLITS, 0, 3) as [$champ, $ecart, $compte, $foi, $origine]) {
            $cartes[] = self::carte(
                $champ, $ecart . ' · ' . $origine . '.', self::nombre($compte),
                'warning', $foi . ' fait foi par défaut', true,
            );
        }

        return [
            'onglet' => $onglet,
            'titre' => 'Conflits à arbitrer',
            'sousTitre' => self::nombre(self::MIROIR[2][1]) . ' conflits de valeur, regroupés par '
                . "champ. Un même écart sur 84 fiches se tranche une fois : la décision pose la "
                . 'règle pour tout le lot et se journalise.',
            'onglets' => self::onglets($onglet),
            'sante' => null,
            'cartes' => $cartes,
            'colonnes' => [
                self::colonne('Champ'), self::colonne('Entités en désaccord'),
                self::colonne('Fiches', 'text-right'), self::colonne('Fait foi'),
                self::colonne("Origine de l'écart"),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::CONFLITS) . ' champs en conflit · ' . self::nombre($fiches)
                . ' fiches concernées',
            'resultat' => '',
            'filtres' => [],
            'tri' => '',
            'actions' => [['Arbitrer en masse', 'ok-circle', true]],
        ];
    }

    /**
     * @return Vue
     */
    private static function formes(string $onglet): array
    {
        $lignes = [];
        $champs = 0;

        foreach (self::FORMES as [$champ, $nature, $compte, $regle]) {
            $champs += $compte;
            $lignes[] = ['cellules' => [
                self::texte($champ, gras: true),
                self::texte($nature, discret: true),
                self::texte(self::nombre($compte), gras: true, aligne: 'text-right'),
                self::jeton($regle, 'bg-primary-4'),
            ]];
        }

        return [
            'onglet' => $onglet,
            'titre' => 'Écarts de forme',
            'sousTitre' => self::nombre(self::MIROIR[1][1]) . ' champs portent la même valeur '
                . "écrite différemment. Ce ne sont pas des conflits : une règle de normalisation "
                . 'les résorbe sans arbitrage humain.',
            'onglets' => self::onglets($onglet),
            'sante' => null,
            'cartes' => [self::carte(
                'Normalisation automatique',
                'Les quatre règles ci-dessous couvrent ' . self::nombre($champs)
                    . ' écarts de forme. Appliquées, le miroir passe de ' . self::score() . ' % à 93 %.',
                '+4 pts', 'rocket', 'Appliquer les 4 règles', false,
            )],
            'colonnes' => [
                self::colonne('Champ'), self::colonne("Nature de l'écart"),
                self::colonne('Fiches', 'text-right'), self::colonne('Règle de normalisation'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::FORMES) . ' règles proposées · ' . self::nombre($champs)
                . ' champs normalisables',
            'resultat' => '',
            'filtres' => [],
            'tri' => '',
            'actions' => [['Appliquer les règles', 'rocket', true]],
        ];
    }

    /**
     * @return Vue
     */
    private static function notifications(string $onglet): array
    {
        $score = self::score();
        $lignes = [];

        foreach (self::NOTIFICATIONS as [$severite, $message, $quand, $destinataire, $etat]) {
            $ouverte = 'ouverte' === $etat;
            $lignes[] = ['cellules' => [
                self::jeton(self::SEVERITES[$severite][0], self::SEVERITES[$severite][1]),
                self::texte($message),
                self::texte($quand, discret: true),
                self::texte($destinataire, discret: true),
                self::jeton($ouverte ? 'Ouverte' : 'Traitée',
                    $ouverte ? 'bg-error-pastel' : 'bg-neutral-200'),
            ]];
        }

        return [
            'onglet' => $onglet,
            'titre' => 'Notifications',
            'sousTitre' => "Ce que la détection a relevé, à qui elle l'a adressé, et ce qui reste "
                . "ouvert. Une notification n'est pas une alerte de plus : elle nomme sa cause et "
                . 'son destinataire.',
            'onglets' => self::onglets($onglet),
            'sante' => null,
            'cartes' => [
                self::carte(
                    '2 notifications hautes',
                    'Le miroir est sous son seuil et 84 conflits sont apparus d\'un seul import. '
                        . "Les deux sont adressées à l'équipe Supply.",
                    '2', 'warning', 'Ouvertes depuis 6 h', true,
                ),
                self::carte(
                    "Seuil d'alerte",
                    'Le miroir déclenche une notification haute sous ' . self::SEUIL . ' %. Il est à '
                        . $score . ' %.',
                    $score . ' %',
                    $score >= self::SEUIL ? 'ok-circle' : 'warning',
                    'Régler le seuil',
                    $score < self::SEUIL,
                ),
            ],
            'colonnes' => [
                self::colonne('Sévérité'), self::colonne('Message'), self::colonne('Quand'),
                self::colonne('Destinataire'), self::colonne('État'),
            ],
            'lignes' => $lignes,
            'pied' => self::ouvertes() . ' ouvertes sur ' . \count(self::NOTIFICATIONS)
                . " · seuil d'alerte " . self::SEUIL . ' %',
            'resultat' => '',
            'filtres' => [],
            'tri' => '',
            'actions' => [['Régler les seuils', 'arrows-out-cardinal', false]],
        ];
    }

    /**
     * @return Vue
     */
    private static function decisions(string $onglet): array
    {
        $lignes = [];

        foreach (self::DECISIONS as [$quand, $qui, $champ, $decision, $portee]) {
            $lignes[] = ['cellules' => [
                self::texte($quand, discret: true),
                self::texte($qui, gras: true),
                self::texte($champ),
                self::jeton($decision, 'bg-success-pastel'),
                self::texte($portee, gras: true, aligne: 'text-right'),
            ]];
        }

        return [
            'onglet' => $onglet,
            'titre' => "Décisions d'arbitrage",
            'sousTitre' => 'Chaque arbitrage est journalisé : qui a tranché, quand, sur quel champ, '
                . 'et pour combien de fiches. Une décision reste réversible depuis le journal des '
                . 'traitements.',
            'onglets' => self::onglets($onglet),
            'sante' => null,
            'cartes' => [self::carte(
                'Dernier arbitrage',
                'M. Rousseau a tranché « Raison sociale » ce matin en faveur de Salesforce, pour '
                    . '84 fiches. Le portail sera corrigé à la prochaine synchronisation.',
                '84', 'ok-circle', 'Voir dans le journal', false,
            )],
            'colonnes' => [
                self::colonne('Quand'), self::colonne('Qui'), self::colonne('Champ'),
                self::colonne('Décision'), self::colonne('Portée', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::DECISIONS) . ' décisions sur 30 jours · toutes réversibles',
            'resultat' => '',
            'filtres' => [],
            'tri' => '',
            'actions' => [['Voir le journal', 'note', false]],
        ];
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
    ): array {
        return [
            'type' => 'texte', 'texte' => $texte, 'gras' => $gras, 'discret' => $discret,
            'aligne' => $aligne, 'teinte' => '', 'fond' => '', 'pastille' => '',
        ];
    }

    /**
     * Valeur précédée de la pastille de son entité d'origine.
     *
     * @return Cellule
     */
    private static function point(
        string $texte,
        string $entite,
        bool $gras = false,
        bool $discret = false,
    ): array {
        return [
            'type' => 'point', 'texte' => $texte, 'gras' => $gras,
            'discret' => $discret || self::ABSENT === $texte,
            'aligne' => 'text-left', 'teinte' => '',
            'fond' => '', 'pastille' => self::ENTITES[$entite] ?? 'bg-neutral-400',
        ];
    }

    /**
     * @return Cellule
     */
    private static function jeton(string $texte, string $fond): array
    {
        return [
            'type' => 'jeton', 'texte' => $texte, 'gras' => true, 'discret' => false,
            'aligne' => 'text-left', 'teinte' => 'bg-neutral-200' === $fond ? 'neutral-500' : 'primary-3',
            'fond' => $fond, 'pastille' => '',
        ];
    }

    /** Part en pourcentage, garde contre un diviseur nul. */
    private static function part(int $compte, int $total): int
    {
        return 0 !== $total ? (int) round($compte / $total * 100) : 0;
    }

    private static function palier(int $part): string
    {
        return match (true) {
            $part >= 75 => 'complet',
            $part >= 60 => 'publiable',
            default => 'insuffisant',
        };
    }

    /** Groupement par milliers à l'espace fine, comme partout dans le back-office. */
    private static function nombre(int $valeur): string
    {
        return number_format($valeur, 0, ',', "\u{202F}");
    }
}
