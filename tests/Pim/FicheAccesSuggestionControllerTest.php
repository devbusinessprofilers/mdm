<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\AeroportReference;
use App\Pim\Entity\GrandeVilleReference;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Endpoint du bouton « Suggérer les accès ». En environnement de test la clé
 * Geoapify est vide : seuls les référentiels statiques (aéroport, grande
 * ville) répondent — gare, métro et tramway sont simplement absents.
 */
#[Group('database')]
final class FicheAccesSuggestionControllerTest extends WebTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testLeBoutonEstLaEtLEndpointSuggereDepuisLesReferentielsStatiques(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('editeur-acces@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château des accès');
        // Fontainebleau : Paris (grande ville) et CDG (aéroport) à ~55-70 km,
        // dans les rayons de suggestion mais hors du seuil « déjà dedans ».
        $localisation = new Localisation();
        $localisation->changeLatitude('48.4040');
        $localisation->changeLongitude('2.7024');
        $entityManager->persist($localisation);
        $lieu->fiche()->changeLocalisation($localisation);
        $entityManager->persist($lieu);
        $entityManager->persist(new AeroportReference('Charles de Gaulle International Airport', 'CDG', 'FR', 49.0097, 2.5479));
        $entityManager->persist(new GrandeVilleReference('Paris', 'FR', 2_100_000, 48.8566, 2.3522));
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Suggérer les accès');
        $csrf = (string) $crawler->filter('#form-fiche')->attr('data-fiche-suggerer-acces-csrf');
        self::assertNotSame('', $csrf);

        $client->request('POST', '/referentiel/fiche/suggerer-acces', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], (string) json_encode(['gamme' => 'lieux', 'id' => $lieu->id(), 'exclus' => []]));

        self::assertResponseIsSuccessful();
        $reponse = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($reponse);
        $parType = array_column($reponse['acces'], null, 'type');
        self::assertArrayHasKey('aeroport', $parType);
        self::assertSame('Charles de Gaulle International Airport (CDG)', $parType['aeroport']['nom']);
        self::assertSame('Voiture', $parType['aeroport']['modeTransport']);
        self::assertArrayHasKey('grande_ville', $parType);
        self::assertSame('Paris', $parType['grande_ville']['nom']);
        // Sans clé Geoapify, pas de gare/métro/tram.
        self::assertCount(2, $reponse['acces']);

        // Les types déjà saisis ne sont pas reproposés.
        $client->request('POST', '/referentiel/fiche/suggerer-acces', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], (string) json_encode(['gamme' => 'lieux', 'id' => $lieu->id(), 'exclus' => ['aeroport']]));
        self::assertResponseIsSuccessful();
        $reponse = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($reponse);
        self::assertSame(['grande_ville'], array_column($reponse['acces'], 'type'));
    }

    private function clearTables(): void
    {
        foreach ([
            'outbox_message',
            'pim_fiche_search',
            'pim_fiche_attribute_value',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
            'pim_aeroport_reference',
            'pim_grande_ville_reference',
            'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
