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
