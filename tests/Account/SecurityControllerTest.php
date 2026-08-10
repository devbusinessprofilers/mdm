<?php

declare(strict_types=1);

namespace App\Tests\Account;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginFormIsPublicAndContainsCsrfProtection(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion au MDM');
        self::assertCount(1, $crawler->filter('form[action="/connexion"][method="post"]'));
        self::assertNotSame('', $crawler->filter('input[name="_csrf_token"]')->attr('value'));
        self::assertCount(1, $crawler->filter('a[href="/mot-de-passe-oublie"]'));
        self::assertCount(0, $crawler->filter('a[href*="register"]'));
    }

    public function testAncienCheminLoginRedirigeVersConnexion(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        self::assertResponseRedirects('/connexion', 301);
    }

    public function testApiIsProtectedByTheSessionFirewall(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api');

        self::assertResponseRedirects('/connexion');
    }

    public function testHealthRemainsPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
    }
}
