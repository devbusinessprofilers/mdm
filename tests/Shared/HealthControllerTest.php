<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReportsDatabaseAndMessengerChecks(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('business-profilers-pim-dam', $data['application']);
        self::assertContains($data['status'], ['ok', 'degraded']);
        self::assertSame('ok', $data['checks']['db']);
        self::assertIsInt($data['checks']['messenger']['failed']);
        self::assertIsArray($data['checks']['messenger']['pending']);
    }
}
