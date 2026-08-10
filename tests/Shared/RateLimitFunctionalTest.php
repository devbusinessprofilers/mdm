<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RateLimitFunctionalTest extends WebTestCase
{
    public function testPublicEndpointExposesRateLimitHeaders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/mot-de-passe-oublie');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('RateLimit-Limit', '30');
        self::assertTrue($client->getResponse()->headers->has('RateLimit-Remaining'));
        self::assertTrue($client->getResponse()->headers->has('RateLimit-Reset'));
        self::assertNotSame('', (string) $client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testIncomingRequestIdIsEchoedBack(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion', server: ['HTTP_X_REQUEST_ID' => 'proxy-abc-123']);
        self::assertResponseHeaderSame('X-Request-Id', 'proxy-abc-123');
    }

    public function testUnrelatedPagesAreNotRateLimited(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');
        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->has('RateLimit-Limit'));
    }
}
