<?php

declare(strict_types=1);

namespace App\Pim\Service\DataTourisme;

/**
 * Lit un flux DATAtourisme déjà téléchargé et décompressé : un répertoire
 * contenant un fichier JSON-LD par point d'intérêt (modèle « flux » de la
 * plateforme, rafraîchi quotidiennement). Chaque fichier est mappé vers un
 * DataTourismePoi normalisé ; le parsing est défensif (le vocabulaire varie),
 * un objet illisible est simplement ignoré.
 *
 * Le flux se configure sur datatourisme.fr (webservice + clé) puis se
 * synchronise dans DATATOURISME_FLUX_DIR — hors du périmètre de ce lecteur.
 */
final readonly class DataTourismeFluxReader
{
    public function __construct(private string $fluxDir)
    {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->fluxDir) && is_dir($this->fluxDir);
    }

    /** @return iterable<DataTourismePoi> */
    public function lire(): iterable
    {
        if (!$this->isConfigured()) {
            return;
        }
        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->fluxDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterateur as $fichier) {
            if (!$fichier instanceof \SplFileInfo || !in_array($fichier->getExtension(), ['json', 'jsonld'], true)) {
                continue;
            }
            if ('index.json' === $fichier->getFilename()) {
                continue;
            }
            $contenu = @file_get_contents($fichier->getPathname());
            if (false === $contenu) {
                continue;
            }
            $donnees = json_decode($contenu, true);
            if (!is_array($donnees)) {
                continue;
            }
            $poi = self::mapper($donnees);
            if (null !== $poi) {
                yield $poi;
            }
        }
    }

    /**
     * Mappe un objet JSON-LD DATAtourisme vers un POI normalisé. null si le nom
     * est absent (objet inexploitable).
     *
     * @param array<string, mixed> $data
     */
    public static function mapper(array $data): ?DataTourismePoi
    {
        $nom = self::fr($data['rdfs:label'] ?? null);
        if (null === $nom) {
            return null;
        }
        $lieu = self::premier($data['isLocatedAt'] ?? null);
        $adresse = self::premier($lieu['schema:address'] ?? null);
        $geo = $lieu['schema:geo'] ?? null;

        return new DataTourismePoi(
            nom: $nom,
            codePostal: self::texte($adresse['schema:postalCode'] ?? null),
            ville: self::texte($adresse['schema:addressLocality'] ?? null) ?? self::fr($adresse['schema:addressLocality'] ?? null),
            latitude: self::texte(is_array($geo) ? ($geo['schema:latitude'] ?? null) : null),
            longitude: self::texte(is_array($geo) ? ($geo['schema:longitude'] ?? null) : null),
            description: self::description($data),
            features: self::features($data['hasFeature'] ?? null),
        );
    }

    /** @param array<string, mixed> $data */
    private static function description(array $data): ?string
    {
        foreach ($data['hasDescription'] ?? [] as $bloc) {
            if (!is_array($bloc)) {
                continue;
            }
            $texte = self::fr($bloc['dc:description'] ?? $bloc['shortDescription'] ?? null);
            if (null !== $texte) {
                return $texte;
            }
        }

        return self::fr($data['rdfs:comment'] ?? null);
    }

    /**
     * @param mixed $features liste de features JSON-LD
     *
     * @return list<string>
     */
    private static function features(mixed $features): array
    {
        if (!is_array($features)) {
            return [];
        }
        $labels = [];
        foreach ($features as $feature) {
            $label = is_array($feature) ? self::fr($feature['rdfs:label'] ?? null) : null;
            if (null !== $label) {
                $labels[] = mb_strtolower($label);
            }
        }

        return array_values(array_unique($labels));
    }

    /** Extrait une valeur française d'un nœud JSON-LD ({"fr":[...]}, {@language,@value}, chaîne, liste). */
    private static function fr(mixed $node): ?string
    {
        if (is_string($node)) {
            return self::texte($node);
        }
        if (!is_array($node)) {
            return null;
        }
        if (isset($node['fr'])) {
            return self::texte(is_array($node['fr']) ? ($node['fr'][0] ?? null) : $node['fr']);
        }
        if (('fr' === ($node['@language'] ?? null)) && isset($node['@value'])) {
            return self::texte($node['@value']);
        }
        // Liste de nœuds localisés : on prend le premier français, sinon le premier.
        foreach ($node as $enfant) {
            if (is_array($enfant) && 'fr' === ($enfant['@language'] ?? null)) {
                return self::texte($enfant['@value'] ?? null);
            }
        }
        $premier = $node[0] ?? null;

        return null === $premier ? null : self::fr($premier);
    }

    /**
     * @param mixed $node liste JSON-LD ou objet unique
     *
     * @return array<string, mixed>
     */
    private static function premier(mixed $node): array
    {
        if (!is_array($node)) {
            return [];
        }
        if (array_is_list($node)) {
            $premier = $node[0] ?? null;

            return is_array($premier) ? $premier : [];
        }

        return $node;
    }

    private static function texte(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
