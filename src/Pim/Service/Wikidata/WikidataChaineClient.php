<?php

declare(strict_types=1);

namespace App\Pim\Service\Wikidata;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Récupère la liste des chaînes et marques hôtelières depuis Wikidata (SPARQL),
 * avec leurs libellés alternatifs, pour construire un dictionnaire de détection.
 * Une requête unique alimente tout le référentiel ; toute erreur est absorbée
 * (l'enrichissement est un confort) et renvoie une liste vide.
 */
#[WithMonologChannel('enrichment')]
final readonly class WikidataChaineClient
{
    /** Chaînes hôtelières (Q1540363) et marques hôtelières (Q23755436), et leurs sous-classes. */
    private const SPARQL = <<<'SPARQL'
        SELECT ?item ?itemLabel ?alt WHERE {
          ?item wdt:P31/wdt:P279* ?type .
          VALUES ?type { wd:Q1540363 wd:Q23755436 }
          OPTIONAL { ?item skos:altLabel ?alt . FILTER(LANG(?alt) IN ("fr","en")) }
          SERVICE wikibase:label { bd:serviceParam wikibase:language "fr,en" . }
        }
        SPARQL;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $endpoint,
    ) {}

    public function isConfigured(): bool
    {
        return '' !== trim($this->endpoint);
    }

    /** @return list<WikidataChaine> */
    public function chaines(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        try {
            $payload = $this->httpClient->request('GET', rtrim($this->endpoint, '/'), [
                'query' => ['query' => self::SPARQL, 'format' => 'json'],
                'headers' => ['Accept' => 'application/sparql-results+json'],
                'timeout' => 30,
            ])->toArray();
        } catch (\Throwable $exception) {
            $this->logger->warning('Wikidata indisponible : {message}', ['message' => $exception->getMessage()]);

            return [];
        }

        return self::mapper($payload);
    }

    /**
     * @param array<string, mixed> $payload réponse SPARQL JSON
     *
     * @return list<WikidataChaine>
     */
    public static function mapper(array $payload): array
    {
        $bindings = $payload['results']['bindings'] ?? null;
        if (!is_array($bindings)) {
            return [];
        }
        /** @var array<string, array{nom: string, alias: array<string, true>}> $parItem */
        $parItem = [];
        foreach ($bindings as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $item = self::valeur($ligne['item'] ?? null);
            $nom = self::valeur($ligne['itemLabel'] ?? null);
            // Un label égal à l'identifiant (Q123…) signifie « pas de libellé » : on ignore.
            if (null === $item || null === $nom || preg_match('/^Q\d+$/', $nom)) {
                continue;
            }
            $parItem[$item] ??= ['nom' => $nom, 'alias' => []];
            $alias = self::valeur($ligne['alt'] ?? null);
            if (null !== $alias && $alias !== $nom) {
                $parItem[$item]['alias'][$alias] = true;
            }
        }

        return array_values(array_map(
            static fn (array $e): WikidataChaine => new WikidataChaine($e['nom'], array_keys($e['alias'])),
            $parItem,
        ));
    }

    private static function valeur(mixed $noeud): ?string
    {
        $valeur = is_array($noeud) ? ($noeud['value'] ?? null) : null;
        if (!is_string($valeur)) {
            return null;
        }
        $valeur = trim($valeur);

        return '' === $valeur ? null : $valeur;
    }
}
