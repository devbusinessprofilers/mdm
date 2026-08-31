<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\GeoapifyClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Mapping des réponses Geoapify vers le shape commun des vérificateurs. */
final class GeoapifyClientTest extends TestCase
{
    public function testUneAdresseSeulePasseParLEndpointSimpleEtEstMappee(): void
    {
        $http = new MockHttpClient([
            new MockResponse((string) json_encode(['results' => [[
                'formatted' => 'Unter den Linden 1, 10117 Berlin, Germany',
                'address_line1' => 'Unter den Linden 1',
                'postcode' => '10117',
                'city' => 'Berlin',
                'lat' => 52.516,
                'lon' => 13.378,
                'rank' => ['confidence' => 0.95],
                'result_type' => 'building',
                'country_code' => 'de',
            ]]])),
        ]);
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        $resultats = $client->verifierLot([
            ['id' => '100', 'adresse' => 'Unter den Linden 1', 'codePostal' => '10117', 'ville' => 'Berlin', 'pays' => 'DE'],
        ]);

        self::assertSame([
            'score' => 0.95,
            'label' => 'Unter den Linden 1, 10117 Berlin, Germany',
            'name' => 'Unter den Linden 1',
            'codePostal' => '10117',
            'ville' => 'Berlin',
            'latitude' => '52.516',
            'longitude' => '13.378',
            // building → niveau rue/numéro : la rue est réécrivable au clic.
            'type' => 'housenumber',
        ], $resultats['100'] ?? null);
    }

    public function testUnResultatHorsDuPaysDemandeVautAucunResultatFiable(): void
    {
        $http = new MockHttpClient([
            new MockResponse((string) json_encode(['results' => [[
                'formatted' => 'Berlin, WI, United States',
                'city' => 'Berlin',
                'rank' => ['confidence' => 0.8],
                'result_type' => 'city',
                'country_code' => 'us',
            ]]])),
        ]);
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        $resultats = $client->verifierLot([
            ['id' => '100', 'adresse' => '', 'codePostal' => '', 'ville' => 'Berlin', 'pays' => 'DE'],
        ]);

        self::assertNull($resultats['100']['score'] ?? null);
        self::assertNull($resultats['100']['label'] ?? null);
    }

    public function testUnLotPasseParUnJobBatchInterrogeJusquAuResultat(): void
    {
        // withOptions() clone le client : on compte les appels via la
        // fabrique partagée plutôt que par getRequestsCount().
        $appels = 0;
        $reponses = [
            new MockResponse((string) json_encode(['id' => 'job-1', 'status' => 'pending']), ['http_code' => 202]),
            new MockResponse((string) json_encode(['status' => 'pending']), ['http_code' => 202]),
            new MockResponse((string) json_encode([
                [
                    'formatted' => 'Unter den Linden 1, 10117 Berlin, Germany',
                    'address_line1' => 'Unter den Linden 1',
                    'postcode' => '10117',
                    'city' => 'Berlin',
                    'lat' => 52.516,
                    'lon' => 13.378,
                    'rank' => ['confidence' => 0.95],
                    'result_type' => 'street',
                    'country_code' => 'de',
                ],
                [
                    'formatted' => 'Alexanderplatz, 10178 Berlin, Germany',
                    'postcode' => '10178',
                    'city' => 'Berlin',
                    'rank' => ['confidence' => 0.7],
                    'result_type' => 'locality',
                    'country_code' => 'de',
                ],
            ])),
        ];
        $http = new MockHttpClient(function () use (&$appels, $reponses): MockResponse {
            return $reponses[$appels++];
        });
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        $resultats = $client->verifierLot([
            ['id' => '100', 'adresse' => 'Unter den Linden 1', 'codePostal' => '10117', 'ville' => 'Berlin', 'pays' => 'DE'],
            ['id' => '200', 'adresse' => 'Alexanderplatz', 'codePostal' => '10178', 'ville' => 'Berlin', 'pays' => 'DE'],
        ]);

        self::assertSame('street', $resultats['100']['type'] ?? null);
        self::assertSame('Berlin', $resultats['100']['ville'] ?? null);
        self::assertSame(0.7, $resultats['200']['score'] ?? null);
        self::assertSame('locality', $resultats['200']['type'] ?? null);
        self::assertSame(3, $appels, 'Soumission puis deux interrogations du job.');
    }

    public function testLAutocompletionMappeLesSuggestionsSurLesChampsDeLocalisation(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            self::assertStringContainsString('/v1/geocode/autocomplete', $url);
            self::assertStringContainsString('filter=countrycode:fr', $url);
            self::assertStringContainsString('lang=fr', $url);

            return new MockResponse((string) json_encode(['results' => [
                [
                    'formatted' => 'Château de Chantilly, 7 Rue du Connétable, 60500 Chantilly, France',
                    // housenumber sort parfois en numérique du JSON Geoapify.
                    'housenumber' => 7,
                    'street' => 'Rue du Connétable',
                    'postcode' => '60500',
                    'city' => 'Chantilly',
                    'state' => 'Hauts-de-France',
                    'county' => 'Oise',
                    'country' => 'France',
                    'country_code' => 'fr',
                    'lat' => 49.194,
                    'lon' => 2.4712,
                ],
                // Hors du pays demandé (le filtre a des trous) : écartée.
                ['formatted' => 'Chantilly, VA, United States', 'country_code' => 'us'],
            ]]));
        });
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        self::assertSame([[
            'label' => 'Château de Chantilly, 7 Rue du Connétable, 60500 Chantilly, France',
            'ruePostale' => '7 Rue du Connétable',
            'codePostal' => '60500',
            'ville' => 'Chantilly',
            'region' => 'Hauts-de-France',
            'departement' => 'Oise',
            'pays' => 'France',
            'countryCode' => 'FR',
            'latitude' => '49.194',
            'longitude' => '2.4712',
        ]], $client->autocomplete('Château de Chantilly', 'FR'));
    }

    public function testLAutocompletionFicheRejoueLeTexteSeulQuandLeNomEtouffeLaRequete(): void
    {
        // « Nom inconnu d'OSM + Paris » ne trouve rien alors que « Paris » seul
        // trouve : le repli doit rejouer le texte saisi sans le nom.
        $urls = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return 1 === count($urls)
                ? new MockResponse((string) json_encode(['results' => []]))
                : new MockResponse((string) json_encode(['results' => [[
                    'formatted' => 'Paris, France',
                    'city' => 'Paris',
                    'country' => 'France',
                    'country_code' => 'fr',
                    'lat' => 48.8566,
                    'lon' => 2.3522,
                ]]]));
        });
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        $suggestions = $client->autocompleteFiche('Business Profilers', 'Paris', 'fr');

        self::assertCount(2, $urls);
        self::assertStringContainsString('text=Business%20Profilers%20Paris', $urls[0]);
        self::assertStringContainsString('text=Paris', $urls[1]);
        self::assertSame('Paris, France', $suggestions[0]['label'] ?? null);
    }

    public function testLAutocompletionFicheSansTexteSaisiNEssaieQueLeNom(): void
    {
        $appels = 0;
        $http = new MockHttpClient(static function () use (&$appels): MockResponse {
            ++$appels;

            return new MockResponse((string) json_encode(['results' => []]));
        });
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        self::assertSame([], $client->autocompleteFiche('Business Profilers', '', 'fr'));
        self::assertSame(1, $appels, 'Sans texte saisi, pas de second essai possible.');
    }

    public function testLAutocompletionSansCleConfigureeNAppelleRien(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            throw new \LogicException('Aucune requête HTTP attendue sans clé.');
        });
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', '', 0);

        self::assertSame([], $client->autocomplete('Château de Chantilly', 'FR'));
    }

    public function testUneLigneSansPaysEstIgnoree(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            throw new \LogicException('Aucune requête HTTP attendue sans pays.');
        });
        $client = new GeoapifyClient($http, 'https://api.geoapify.test', 'cle-de-test', 0);

        self::assertSame([], $client->verifierLot([
            ['id' => '100', 'adresse' => 'Quelque part', 'codePostal' => '', 'ville' => ''],
        ]));
    }
}
