<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

/**
 * Contenu de la modale « Extraire d'un document », sur l'éditeur de fiche.
 *
 * Maquette : `MDM prototype.dc.html`, état `extract` de la page `fiche`.
 *
 * Trois temps : on dépose, l'IA lit, l'humain valide **champ par champ**. Rien
 * n'est écrit dans la fiche sans accord, et la provenance affichée est la page
 * du document — pas « l'IA ».
 *
 * @phpstan-type Etape array{cle: string, numero: string, libelle: string}
 * @phpstan-type Passe array{libelle: string, detail: string, etat: string,
 *     icone: string, jeton: string, fond: string}
 * @phpstan-type Valeur array{champ: string, lu: string, actuel: string, page: string,
 *     confiance: int, verdict: string, libelleVerdict: string, fondVerdict: string,
 *     teinteVerdict: string, teinteBarre: string, deja: bool}
 */
final class ExtractionMaquette
{
    /** @var list<string> */
    public const ETAPES = ['depot', 'lecture', 'valid'];

    /** Confiance au-delà de laquelle une valeur peut être acceptée en lot. */
    private const CONFIANCE_ELEVEE = 4;

    public const FICHIER = 'Plaquette-commerciale-Montvillargenne-2026.pdf';
    public const FICHIER_META = 'PDF · 4,2 Mo · 12 pages · déposé il y a quelques secondes';

    /** @var list<string> */
    public const TYPES_ACCEPTES = ['PDF', 'Word', 'Excel', 'PowerPoint', 'Image'];

    /**
     * Les quatre passes de lecture, dans l'ordre.
     *
     * Libellé, état, détail.
     *
     * @var list<array{string, string, string}>
     */
    private const PASSES = [
        ['OCR des 12 pages', 'terminé', '12 pages lues, 4 218 mots'],
        ['Détection des champs', 'terminé', '9 valeurs candidates repérées'],
        ['Rapprochement au référentiel', 'en cours', 'comparaison aux 210 champs de la fiche'],
        ['Calcul de confiance', 'en file', 'par valeur, selon la netteté de la source'],
    ];

    /**
     * Les neuf valeurs lues.
     *
     * Champ, valeur lue, valeur actuelle de la fiche, page source, confiance
     * sur 4, verdict.
     *
     * @var list<array{string, string, string, string, int, string}>
     */
    private const VALEURS = [
        ['Capacité en configuration classe', '180 personnes', '—', 'p. 4', 4, 'accepter'],
        ['Capacité cocktail', '480 personnes', '—', 'p. 4', 4, 'accepter'],
        ['Nombre de salles de réunion', '27', '9', 'p. 3', 4, 'conflit'],
        ['Surface de la plus grande salle', '220 m²', '—', 'p. 4', 4, 'accepter'],
        ['Certifications RSE', 'Green Key, Clef Verte', '—', 'p. 11', 3, 'relire'],
        ['Tarif séminaire résidentiel', '249 € HT / pers.', '—', 'p. 9', 3, 'relire'],
        ['Nombre de chambres', '120', '120', 'p. 2', 4, 'identique'],
        ['Descriptif des salles', 'Douze salles modulables de 20 à 220 m²…', '—', 'p. 5', 2, 'relire'],
        ['Coordonnées GPS', '49,1912 · 2,4204', '—', 'p. 12', 2, 'relire'],
    ];

    /** Libellé, fond et teinte de chaque verdict. @var array<string, array{string, string, string}> */
    private const VERDICTS = [
        'accepter' => ['Complète la fiche', 'bg-success-pastel', 'primary-3'],
        'conflit' => ['Contredit la fiche', 'bg-error-pastel', 'primary-3'],
        'relire' => ['À relire', 'bg-peach-pastel', 'primary-3'],
        'identique' => ['Déjà identique', 'bg-neutral-200', 'neutral-500'],
    ];

    /** Glyphe et fond de chaque état de passe. @var array<string, array{string, string, string}> */
    private const ETATS_PASSE = [
        'terminé' => ['ok-circle', 'Terminé', 'bg-success-pastel'],
        'en cours' => ['spinner', 'En cours', 'bg-primary-4'],
        'en file' => ['caret-right', 'En file', 'bg-neutral-200'],
    ];

    /**
     * Le fil des trois étapes.
     *
     * @return list<Etape>
     */
    public static function etapes(): array
    {
        $libelles = ['Déposer', 'Lecture', 'Valider'];
        $etapes = [];

        foreach (self::ETAPES as $rang => $cle) {
            $etapes[] = [
                'cle' => $cle,
                'numero' => (string) ($rang + 1),
                'libelle' => $libelles[$rang],
            ];
        }

        return $etapes;
    }

    /**
     * @return list<Passe>
     */
    public static function passes(): array
    {
        $passes = [];

        foreach (self::PASSES as [$libelle, $etat, $detail]) {
            [$icone, $jeton, $fond] = self::ETATS_PASSE[$etat];

            $passes[] = [
                'libelle' => $libelle, 'detail' => $detail, 'etat' => $etat,
                'icone' => $icone, 'jeton' => $jeton, 'fond' => $fond,
            ];
        }

        return $passes;
    }

    /**
     * @return list<Valeur>
     */
    public static function valeurs(): array
    {
        $valeurs = [];

        foreach (self::VALEURS as [$champ, $lu, $actuel, $page, $confiance, $verdict]) {
            [$libelle, $fond, $teinte] = self::VERDICTS[$verdict];

            $valeurs[] = [
                'champ' => $champ,
                'lu' => $lu,
                // Un tiret n'est pas une valeur : c'est un champ que la fiche n'a pas.
                'actuel' => self::vide($actuel) ? 'Champ vide' : $actuel,
                'page' => $page,
                'confiance' => $confiance,
                'verdict' => $verdict,
                'libelleVerdict' => $libelle,
                'fondVerdict' => $fond,
                'teinteVerdict' => $teinte,
                'teinteBarre' => match (true) {
                    $confiance >= 4 => 'bg-success',
                    $confiance >= 3 => 'bg-peach',
                    default => 'bg-error',
                },
                'deja' => self::dejaIdentique($verdict),
            ];
        }

        return $valeurs;
    }

    private static function vide(string $valeur): bool
    {
        return '—' === $valeur;
    }

    /** Une valeur déjà portée par la fiche : il n'y a rien à accepter. */
    private static function dejaIdentique(string $verdict): bool
    {
        return 'identique' === $verdict;
    }

    /**
     * Le décompte du bandeau de validation.
     *
     * ÉCART : le handoff annonce « 4 champs vides, 1 contradiction, 3 à relire,
     * 1 déjà identique » alors que ses propres données donnent 3 et 4. Le
     * décompte est recalculé à partir des verdicts — sans quoi la ligne
     * contredit le tableau qu'elle résume.
     */
    public static function decompte(): string
    {
        $parVerdict = [];

        foreach (self::VALEURS as [, , , , , $verdict]) {
            $parVerdict[$verdict] = ($parVerdict[$verdict] ?? 0) + 1;
        }

        $morceaux = [];

        foreach ([
            'accepter' => 'complètent la fiche',
            'conflit' => 'contradiction',
            'relire' => 'à relire',
            'identique' => 'déjà identique',
        ] as $verdict => $libelle) {
            $compte = $parVerdict[$verdict] ?? 0;

            if (0 !== $compte) {
                $morceaux[] = $compte . ' ' . $libelle . ('conflit' === $verdict && $compte > 1 ? 's' : '');
            }
        }

        return \count(self::VALEURS) . ' valeurs lues · ' . implode(', ', $morceaux);
    }

    /**
     * Les valeurs qu'une acceptation en lot peut absorber : confiance maximale,
     * et pas déjà identiques à la fiche.
     */
    public static function acceptablesEnLot(): int
    {
        return \count(array_filter(
            self::VALEURS,
            static fn (array $v): bool => $v[4] >= self::CONFIANCE_ELEVEE && 'identique' !== $v[5],
        ));
    }

    /** Libellé du bouton d'avancement, selon l'étape. */
    public static function libelleSuivant(string $etape): string
    {
        return match ($etape) {
            'lecture' => 'Voir les ' . \count(self::VALEURS) . ' valeurs lues',
            'valid' => 'Appliquer les valeurs acceptées',
            default => "Lancer l'extraction",
        };
    }
}
