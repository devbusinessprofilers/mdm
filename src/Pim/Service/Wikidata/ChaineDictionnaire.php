<?php

declare(strict_types=1);

namespace App\Pim\Service\Wikidata;

/**
 * Dictionnaire de détection de chaîne/marque hôtelière : un référentiel interne
 * des grands groupes (motifs sûrs), enrichi des chaînes remontées de Wikidata.
 * La détection cherche le motif le plus long présent (en mots entiers) dans le
 * nom de l'établissement, pour éviter les faux positifs sur des mots génériques.
 */
final class ChaineDictionnaire
{
    /** Longueur minimale d'un motif normalisé pour être discriminant. */
    private const MOTIF_MIN = 4;

    /** Référentiel interne : nom canonique → motifs (marques et enseignes du groupe). */
    private const SEED = [
        'Accor' => ['accor', 'ibis', 'ibis budget', 'ibis styles', 'novotel', 'mercure', 'grand mercure', 'sofitel', 'pullman', 'mgallery', 'raffles', 'adagio', 'mama shelter'],
        'Hilton' => ['hilton', 'canopy by hilton', 'hampton by hilton', 'doubletree', 'waldorf astoria', 'conrad', 'embassy suites'],
        'Marriott' => ['marriott', 'courtyard', 'renaissance hotel', 'sheraton', 'westin', 'le meridien', 'moxy', 'autograph collection', 'residence inn'],
        'InterContinental (IHG)' => ['intercontinental', 'holiday inn', 'crowne plaza', 'kimpton', 'staybridge'],
        'Best Western' => ['best western'],
        'Radisson' => ['radisson', 'radisson blu', 'park inn'],
        'Louvre Hotels' => ['kyriad', 'campanile', 'premiere classe', 'golden tulip', 'tulip inn'],
        'B&B Hotels' => ['b&b hotels', 'b and b hotels'],
        'Logis' => ['logis hotels'],
        'Relais & Châteaux' => ['relais chateaux', 'relais et chateaux'],
        'NH Hotel Group' => ['nh hotels', 'nh collection'],
    ];

    /** @var array<string, list<string>> nom canonique → motifs normalisés discriminants */
    private array $entrees = [];

    /** @param list<WikidataChaine> $chaines chaînes remontées de Wikidata */
    public static function depuis(array $chaines = []): self
    {
        $dico = new self();
        foreach (self::SEED as $nom => $motifs) {
            $dico->ajouter($nom, $motifs);
        }
        foreach ($chaines as $chaine) {
            $dico->ajouter($chaine->nom, [$chaine->nom, ...$chaine->alias]);
        }

        return $dico;
    }

    public function estVide(): bool
    {
        return [] === $this->entrees;
    }

    /** Chaîne canonique détectée dans un nom d'établissement, ou null. */
    public function detecter(string $nomEtablissement): ?string
    {
        $nom = self::normaliser($nomEtablissement);
        if (' ' === $nom) {
            return null;
        }
        $meilleur = null;
        $meilleureLongueur = 0;
        foreach ($this->entrees as $canonique => $motifs) {
            foreach ($motifs as $motif) {
                if (mb_strlen($motif) > $meilleureLongueur && str_contains($nom, ' '.$motif.' ')) {
                    $meilleur = $canonique;
                    $meilleureLongueur = mb_strlen($motif);
                }
            }
        }

        return $meilleur;
    }

    /** @param list<string> $motifs */
    private function ajouter(string $nom, array $motifs): void
    {
        foreach ($motifs as $motif) {
            $normalise = trim(self::normaliser($motif));
            if (mb_strlen($normalise) >= self::MOTIF_MIN) {
                $this->entrees[$nom][] = $normalise;
            }
        }
        if (isset($this->entrees[$nom])) {
            $this->entrees[$nom] = array_values(array_unique($this->entrees[$nom]));
        }
    }

    /** Minuscule, sans accents, ponctuation réduite à des espaces, entourée d'espaces (frontières de mots). */
    private static function normaliser(string $valeur): string
    {
        $valeur = mb_strtolower($valeur);
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);
        if (is_string($translit)) {
            $valeur = $translit;
        }
        $valeur = preg_replace('/[^a-z0-9]+/', ' ', $valeur) ?? '';

        return ' '.trim($valeur).' ';
    }
}
