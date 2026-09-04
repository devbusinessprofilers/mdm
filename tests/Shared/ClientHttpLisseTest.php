<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Http\ClientHttpLisse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ClientHttpLisseTest extends TestCase
{
    public function testUn429EstReessayeUneFoisApresRetryAfter(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 429, 'response_headers' => ['retry-after' => '1']]),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);
        $transport = new ClientHttpLisse($http, 0.0);
        $debut = microtime(true);
        $response = $transport->request('GET', 'https://api.example.test/x');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $http->getRequestsCount());
        self::assertGreaterThanOrEqual(1.0, microtime(true) - $debut, 'Le réessai attend Retry-After.');
    }

    public function testUnSecond429EstRenduTelQuel(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 429, 'response_headers' => ['retry-after' => '1']]),
            new MockResponse('', ['http_code' => 429, 'response_headers' => ['retry-after' => '1']]),
        ]);
        $response = (new ClientHttpLisse($http, 0.0))->request('GET', 'https://api.example.test/x');
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testLesRequetesSontEspaceesDeLIntervalleMinimal(): void
    {
        $http = new MockHttpClient([new MockResponse('a'), new MockResponse('b')]);
        $transport = new ClientHttpLisse($http, 0.2);
        $debut = microtime(true);
        $transport->request('GET', 'https://api.example.test/1');
        $transport->request('GET', 'https://api.example.test/2');
        self::assertGreaterThanOrEqual(0.19, microtime(true) - $debut);
    }
}
