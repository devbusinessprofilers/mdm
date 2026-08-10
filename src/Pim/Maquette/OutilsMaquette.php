<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de l'écran « Outils » — le journal des traitements.
 *
 * Maquette : `MDM prototype.dc.html`, pages `journal` / `outils`.
 *
 * Quatre outils partagent un rail ; seul le **journal des traitements** est
 * intégré — imports, exports, synchronisations, mises à jour massives et
 * campagnes IA, en un seul endroit quand quelque chose s'est mal passé.
 *
 * La bibliothèque de médias a quitté ce rail : le prototype en a fait une
 * entrée de barre à part entière, avec ses huit onglets.
 *
 * @phpstan-type Entree array{cle: string, libelle: string, icone: string, badge: string,
 *     actif: bool, integre: bool}
 * @phpstan-type Indicateur array{libelle: string, valeur: string, note: string,
 *     icone: string, fond: string}
 * @phpstan-type Cellule array{type: string, texte: string, note: string, gras: bool,
 *     discret: bool, aligne: string, teinte: string, fond: string}
 * @phpstan-type Colonne array{libelle: string, aligne: string}
 * @phpstan-type Vue array{cle: string, titre: string, sousTitre: string, entrees: list<Entree>,
 *     indicateurs: list<Indicateur>, colonnes: list<Colonne>,
 *     lignes: list<array{cellules: list<Cellule>}>, pied: string}
 */
final class OutilsMaquette
{
    /**
     * Le rail des outils.
     *
     * Clé, libellé, glyphe, pastille, intégré.
     *
     * @var list<array{string, string, string, string, bool}>
     */
    private const OUTILS = [
        ['masse', 'Mise à jour massive', 'pencil', '', false],
        ['journal', 'Journal des traitements', 'spinner', '25', true],
        ['campagnes', 'Campagnes IA', 'rocket', '200', false],
        ['imports', 'Imports & exports', 'upload-simple', '', false],
    ];

    /**
     * Origine, traitement, référence, état, volume, erreurs, quand, auteur.
     *
     * @var list<array{string, string, string, string, int, int, string, string}>
     */
    private const TRAITEMENTS = [
        ['Mise à jour', 'Publier sur 8 canaux', 'MAJ-2026-0841', 'warn', 6218, 34, 'il y a 12 min', 'M. Rousseau'],
        ['Synchronisation', 'Salesforce → MDM · delta', 'SYN-2026-4412', 'run', 1842, 0, 'en cours', 'Automatique'],
        ['Campagne IA', 'Enrichir les descriptions', 'IA-2026-0217', 'done', 200, 0, 'il y a 1 h', 'C. Berthier'],
        ['Import', 'Catalogue traiteurs · lenotre.xlsx', 'IMP-2026-1130', 'fail', 348, 348, 'il y a 2 h', 'A. Dufour'],
        ['Export', 'Marketplace · flux quotidien', 'EXP-2026-8802', 'done', 15906, 0, 'il y a 3 h', 'Automatique'],
        ['Mise à jour', 'Affecter un contributeur', 'MAJ-2026-0840', 'done', 142, 0, 'il y a 4 h', 'L. Garnier'],
        ['Synchronisation', 'MDM → portail prestataire', 'SYN-2026-4411', 'warn', 4218, 7, 'il y a 5 h', 'Automatique'],
        ['Import', 'Photos Bedouk · lot 4', 'IMP-2026-1129', 'done', 1284, 0, 'hier', 'M. Rousseau'],
    ];

    /** Libellé et fond de chaque état de traitement. @var array<string, array{string, string}> */
    private const ETATS = [
        'queue' => ['En file', 'bg-neutral-200'],
        'run' => ['En cours', 'bg-primary-4'],
        'done' => ['Terminé', 'bg-success-pastel'],
        'warn' => ['Terminé avec erreurs', 'bg-peach-pastel'],
        'fail' => ['Échoué', 'bg-error-pastel'],
    ];



    /**
     * @return Vue
     */
    public static function vue(): array
    {
        return self::journal();
    }

    /**
     * @return list<Entree>
     */
    private static function entrees(string $actif): array
    {
        $entrees = [];

        foreach (self::OUTILS as [$cle, $libelle, $icone, $badge, $integre]) {
            $entrees[] = [
                'cle' => $cle,
                'libelle' => $libelle,
                'icone' => $icone,
                'badge' => $badge,
                'actif' => $cle === $actif,
                'integre' => $integre,
            ];
        }

        return $entrees;
    }

    /**
     * @return Vue
     */
    private static function journal(): array
    {
        $lignes = [];

        foreach (self::TRAITEMENTS as [$origine, $nom, $reference, $etat, $volume, $erreurs, $quand, $auteur]) {
            $rejouable = \in_array($etat, ['fail', 'warn'], true);

            $lignes[] = ['cellules' => [
                self::texte($origine, discret: true),
                self::duo($nom, $reference),
                self::jeton(self::ETATS[$etat][0], self::ETATS[$etat][1]),
                self::texte(self::nombre($volume), gras: true, aligne: 'text-right'),
                self::texte(0 !== $erreurs ? self::nombre($erreurs) : '—',
                    gras: true, discret: 0 === $erreurs, aligne: 'text-right'),
                self::duo($quand, $auteur),
                self::lien($rejouable ? 'Rejouer' : 'Détail'),
            ]];
        }

        return [
            'cle' => 'journal',
            'titre' => 'Journal des traitements',
            'sousTitre' => 'Imports, exports, synchronisations, mises à jour massives et campagnes '
                . "IA — un seul endroit quand quelque chose s'est mal passé.",
            'entrees' => self::entrees('journal'),
            'indicateurs' => [
                self::indicateur('Traitements', '218', 'sur 30 jours', 'info-circle', 'bg-neutral-200'),
                self::indicateur('En cours ou en file', '7', '3 actifs, 4 planifiés', 'spinner', 'bg-primary-4'),
                self::indicateur('Terminés', '186', '85 % sans erreur', 'ok-circle', 'bg-success-pastel'),
                self::indicateur('Avec erreurs', '21', 'rejouables en un clic', 'warning', 'bg-peach-pastel'),
                self::indicateur('Échoués', '4', 'aucune fiche touchée', 'warning', 'bg-error-pastel'),
            ],
            'colonnes' => [
                self::colonne('Origine'), self::colonne('Traitement'), self::colonne('État'),
                self::colonne('Volume', 'text-right'), self::colonne('Erreurs', 'text-right'),
                self::colonne('Quand'), self::colonne('', 'text-right'),
            ],
            'lignes' => $lignes,
            'pied' => \count(self::TRAITEMENTS) . ' traitements affichés · '
                . self::enErreur() . " rejouables · volume cumulé " . self::nombre(self::volume()),
        ];
    }


    private static function enErreur(): int
    {
        return \count(array_filter(
            self::TRAITEMENTS,
            static fn (array $t): bool => \in_array($t[3], ['fail', 'warn'], true),
        ));
    }

    private static function volume(): int
    {
        $total = 0;

        foreach (self::TRAITEMENTS as $traitement) {
            $total += $traitement[4];
        }

        return $total;
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
    ): array {
        return self::cellule('texte', $texte, gras: $gras, discret: $discret, aligne: $aligne);
    }

    /**
     * Valeur sur deux lignes : l'intitulé, puis sa précision en dessous.
     *
     * @return Cellule
     */
    private static function duo(string $texte, string $note): array
    {
        return self::cellule('duo', $texte, note: $note, gras: true);
    }

    /**
     * @return Cellule
     */
    private static function jeton(string $texte, string $fond): array
    {
        return self::cellule('jeton', $texte, gras: true, fond: $fond,
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
    private static function cellule(
        string $type,
        string $texte,
        string $note = '',
        bool $gras = false,
        bool $discret = false,
        string $aligne = 'text-left',
        string $teinte = '',
        string $fond = '',
    ): array {
        return [
            'type' => $type, 'texte' => $texte, 'note' => $note, 'gras' => $gras,
            'discret' => $discret, 'aligne' => $aligne, 'teinte' => $teinte, 'fond' => $fond,
        ];
    }

    /** Groupement par milliers à l'espace fine, comme partout dans le back-office. */
    private static function nombre(int $valeur): string
    {
        return number_format($valeur, 0, ',', "\u{202F}");
    }
}
