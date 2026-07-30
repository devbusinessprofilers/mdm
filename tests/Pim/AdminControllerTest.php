<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminControllerTest extends WebTestCase
{
    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }
}
