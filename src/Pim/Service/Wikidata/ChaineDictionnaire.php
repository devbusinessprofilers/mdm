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

    /**
     * Référentiel interne : groupe → marques/enseignes, en casse d'affichage
     * (le libellé du motif gagnant est celui proposé à l'arbitrage, il doit
     * correspondre aux marques de la LOV « Groupe et chaîne hôtelière »).
     */
    private const SEED = [
        'Accor' => ['Accor', 'Ibis', 'Ibis Budget', 'Ibis Styles', 'Novotel', 'Mercure', 'Grand Mercure', 'Sofitel', 'Pullman', 'Mgallery', 'Raffles', 'Adagio', 'Mama Shelter'],
        'Hilton' => ['Hilton', 'Canopy by Hilton', 'Hampton by Hilton', 'DoubleTree', 'Waldorf Astoria', 'Conrad', 'Embassy Suites'],
        'Marriott' => ['Marriott', 'Courtyard', 'Renaissance Hotel', 'Sheraton', 'Westin', 'Le Méridien', 'Moxy', 'Autograph Collection', 'Residence Inn'],
        'InterContinental (IHG)' => ['InterContinental', 'Holiday Inn', 'Crowne Plaza', 'Kimpton', 'Staybridge'],
        'Best Western' => ['Best Western'],
        'Radisson' => ['Radisson', 'Radisson Blu', 'Park Inn'],
        'Louvre Hotels' => ['Kyriad', 'Campanile', 'Première Classe', 'Golden Tulip', 'Tulip Inn'],
        'B&B Hotels' => ['B&B Hotels', 'B and B Hotels'],
        'Logis' => ['Logis Hotels'],
        'Relais & Châteaux' => ['Relais Châteaux', 'Relais et Châteaux'],
        'NH Hotel Group' => ['NH Hotels', 'NH Collection'],
    ];

    /** @var array<string, array<string, string>> nom canonique (groupe) → [motif normalisé → libellé d'enseigne] */
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

    /**
     * Enseigne détectée dans un nom d'établissement (motif le plus long), avec
     * son groupe propriétaire, ou null. C'est l'ENSEIGNE (« Mercure ») qui est
     * proposée à l'arbitrage — elle correspond aux marques de la LOV — le
     * groupe (« Accor ») reste en information.
     */
    public function detecter(string $nomEtablissement): ?ChaineDetectee
    {
        $nom = self::normaliser($nomEtablissement);
        if (' ' === $nom) {
            return null;
        }
        $meilleur = null;
        $meilleureLongueur = 0;
        foreach ($this->entrees as $canonique => $motifs) {
            foreach ($motifs as $motif => $enseigne) {
                if (mb_strlen($motif) > $meilleureLongueur && str_contains($nom, ' '.$motif.' ')) {
                    $meilleur = new ChaineDetectee(enseigne: $enseigne, groupe: $canonique);
                    $meilleureLongueur = mb_strlen($motif);
                }
            }
        }

        return $meilleur;
    }

    /** @param list<string> $motifs enseignes en casse d'affichage */
    private function ajouter(string $nom, array $motifs): void
    {
        foreach ($motifs as $motif) {
            $normalise = trim(self::normaliser($motif));
            if (mb_strlen($normalise) >= self::MOTIF_MIN && !isset($this->entrees[$nom][$normalise])) {
                $this->entrees[$nom][$normalise] = trim($motif);
            }
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
