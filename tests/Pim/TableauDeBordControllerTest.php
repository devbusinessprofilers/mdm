<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le tableau de bord est la page d'accueil : la racine le sert.
 */
final class TableauDeBordControllerTest extends WebTestCase
{
    public function testLaRacineSertLeTableauDeBord(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('Bonjour Marie', $crawler->filter('h1')->text());
        self::assertCount(4, $crawler->filter('[data-tableau-de-bord-target="zone"]'));
    }

    public function testLAncienCheminResteServi(): void
    {
        $client = self::createClient();
        $client->request('GET', '/tableau-de-bord');

        self::assertResponseIsSuccessful();
    }

    /**
     * Les cinq états de la maquette passent par la query string.
     */
    public function testLesCinqEtatsRepondent(): void
    {
        $client = self::createClient();

        foreach (['nominal', 'vide', 'fort', 'chargement', 'param'] as $etat) {
            $client->request('GET', '/?etat=' . $etat);
            self::assertResponseIsSuccessful('État « ' . $etat .' »');
        }
    }

    /**
     * Un état inconnu retombe sur la vue nominale plutôt que d'échouer.
     */
    public function testUnEtatInconnuRetombeSurLeNominal(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/?etat=nawak');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('126 éléments à traiter', $crawler->filter('h1 + p')->text());
    }
}
