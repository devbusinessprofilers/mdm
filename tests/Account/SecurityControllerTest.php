<?php

declare(strict_types=1);

namespace App\Tests\Account;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testEmailStepIsPublicAndContainsCsrfProtection(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Identifiez-vous');
        self::assertCount(1, $crawler->filter('form[action="/connexion/identifiant"][method="post"]'));
        self::assertNotSame('', $crawler->filter('input[name="_csrf_token"]')->attr('value'));
        self::assertCount(0, $crawler->filter('input[name="password"]'));
        self::assertCount(0, $crawler->filter('a[href*="register"]'));
    }

    public function testEmailStepOpensThePasswordStep(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Continuer')->form(['email' => 'user@example.com']));

        self::assertResponseRedirects('/connexion/mot-de-passe');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connectez-vous');
        self::assertSelectorTextContains('main', 'user@example.com');
        self::assertCount(1, $crawler->filter('form[action="/connexion"][method="post"]'));
        self::assertSame('user@example.com', $crawler->filter('input[name="email"]')->attr('value'));
        self::assertNotSame('', $crawler->filter('input[name="_csrf_token"]')->attr('value'));
        self::assertCount(1, $crawler->filter('a[href="/mot-de-passe-oublie"]'));
    }

    public function testPasswordStepWithoutEmailFallsBackToTheEmailStep(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion/mot-de-passe');

        self::assertResponseRedirects('/connexion');
    }

    public function testInvalidEmailStaysOnTheEmailStepWithAnError(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Continuer')->form(['email' => 'pas-un-email']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('h1', 'Identifiez-vous');
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
