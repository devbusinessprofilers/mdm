<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\RechercheEntrepriseClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RechercheEntrepriseClientTest extends TestCase
{
    public function testRetriesWithoutCodePostalWhenFilteredSearchIsEmpty(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return 1 === count($requests)
                ? new MockResponse('{"results": []}')
                : new MockResponse(json_encode(['results' => [[
                    'nom_complet' => 'BUSINESS PROFILERS',
                    'siren' => '480674100',
                    'siege' => [
                        'siret' => '48067410000031',
                        'numero_voie' => '1',
                        'type_voie' => 'AVENUE',
                        'libelle_voie' => 'DU GENERAL DE GAULLE',
                        'code_postal' => '60500',
                        'libelle_commune' => 'CHANTILLY',
                        'latitude' => '49.19',
                        'longitude' => '2.46',
                    ],
                ]]], JSON_THROW_ON_ERROR));
        });
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $info = $client->findBest('Business Profilers', '60460');

        self::assertCount(2, $requests);
        self::assertStringContainsString('code_postal=60460', $requests[0]);
        self::assertStringNotContainsString('code_postal', $requests[1]);
        self::assertNotNull($info);
        self::assertSame('1 AVENUE DU GENERAL DE GAULLE', $info->rue);
        self::assertSame('FR39480674100', $info->numeroTva);
    }

    public function testReturnsNullOnTransportError(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'DNS failure']));
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        self::assertNull($client->findBest('Business Profilers', null));
    }
}
