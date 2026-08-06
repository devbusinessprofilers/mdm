<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class MetricsControllerTest extends WebTestCase
{
    private const TOKEN = 'test-metrics-token';

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        putenv('METRICS_TOKEN='.self::TOKEN);
        $_ENV['METRICS_TOKEN'] = self::TOKEN;
        $_SERVER['METRICS_TOKEN'] = self::TOKEN;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('METRICS_TOKEN');
        unset($_ENV['METRICS_TOKEN'], $_SERVER['METRICS_TOKEN']);

        parent::tearDown();
    }

    public function testMetricsAreHiddenWithoutValidToken(): void
    {
        $client = self::createClient();
        $client->request('GET', '/metrics');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/metrics', server: ['HTTP_AUTHORIZATION' => 'Bearer wrong-token']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testMetricsExposePrometheusTextFormat(): void
    {
        $client = self::createClient();
        $client->request('GET', '/metrics', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('# TYPE app_http_requests_total counter', $body);
        self::assertStringContainsString('app_messenger_failed_messages ', $body);
    }
}
