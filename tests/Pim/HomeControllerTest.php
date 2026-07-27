<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePageIsAvailableAndEmpty(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('', trim($crawler->filter('body')->text()));
    }
}
