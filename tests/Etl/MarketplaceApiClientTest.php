<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\MarketplaceApiClient;
use App\Etl\Service\MarketplaceApiException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MarketplaceApiClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    public function testUpsertAuthenticatesThenPushesTheSnapshot(): void
    {
        $client = $this->client([
            new MockResponse('{"token":"jwt-1"}'),
            new MockResponse('', ['http_code' => 200]),
        ]);
        $client->upsertFiche(123, ['code' => 123, 'type' => 'lieu']);

        self::assertCount(2, $this->requests);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame('https://marketplace.test/api/login_check', $this->requests[0]['url']);
        self::assertSame('PUT', $this->requests[1]['method']);
        self::assertSame('https://marketplace.test/api/pim/fiches/123', $this->requests[1]['url']);
        self::assertContains('Authorization: Bearer jwt-1', $this->requests[1]['options']['headers']);
    }

    public function testTokenIsReusedAcrossCallsAndRenewedOn401(): void
    {
        $client = $this->client([
            new MockResponse('{"token":"jwt-1"}'),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('', ['http_code' => 401]),
            new MockResponse('{"token":"jwt-2"}'),
            new MockResponse('', ['http_code' => 204]),
        ]);
        $client->upsertFiche(1, []);
        $client->upsertFiche(2, []);

        self::assertCount(5, $this->requests);
        self::assertSame('https://marketplace.test/api/pim/fiches/2', $this->requests[2]['url']);
        self::assertSame('https://marketplace.test/api/login_check', $this->requests[3]['url']);
        self::assertContains('Authorization: Bearer jwt-2', $this->requests[4]['options']['headers']);
    }

    public function testStaleSequenceConflictIsASuccess(): void
    {
        $client = $this->client([
            new MockResponse('{"token":"jwt-1"}'),
            new MockResponse('', ['http_code' => 409]),
        ]);
        $client->upsertFiche(123, []);

        self::assertCount(2, $this->requests);
    }

    public function testRefusedSnapshotThrowsForMessengerRetry(): void
    {
        $client = $this->client([
            new MockResponse('{"token":"jwt-1"}'),
            new MockResponse('', ['http_code' => 500]),
        ]);

        $this->expectException(MarketplaceApiException::class);
        $client->upsertFiche(123, []);
    }

    public function testRemoveToleratesUnknownFiche(): void
    {
        $client = $this->client([
            new MockResponse('{"token":"jwt-1"}'),
            new MockResponse('', ['http_code' => 404]),
        ]);
        $client->removeFiche(123, '01JSEQUENCE');

        self::assertSame('DELETE', $this->requests[1]['method']);
        self::assertStringContainsString('sequence=01JSEQUENCE', $this->requests[1]['url']);
    }

    public function testRejectedAuthenticationThrows(): void
    {
        $client = $this->client([new MockResponse('', ['http_code' => 401])]);

        $this->expectException(MarketplaceApiException::class);
        $client->upsertFiche(123, []);
    }

    public function testMissingConfigurationThrows(): void
    {
        $client = new MarketplaceApiClient(
            new MockHttpClient(),
            new NullLogger(),
            '',
            'pim',
            'secret',
        );

        self::assertFalse($client->isConfigured());
        $this->expectException(MarketplaceApiException::class);
        $client->upsertFiche(123, []);
    }

    /** @param list<MockResponse> $responses */
    private function client(array $responses): MarketplaceApiClient
    {
        $this->requests = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
            $response = array_shift($responses);
            self::assertNotNull($response, 'Requête HTTP inattendue : '.$method.' '.$url);

            return $response;
        });

        return new MarketplaceApiClient(
            $http,
            new NullLogger(),
            'https://marketplace.test',
            'pim',
            'secret',
        );
    }
}
