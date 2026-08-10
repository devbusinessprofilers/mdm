<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Listes et éditeurs par gamme (Restaurant, Activité, Service) sur le patron du Lieu. */
#[Group('database')]
final class FicheGammeEditeurTest extends WebTestCase
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

    public function testListesEtEditeursDesTroisGammes(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('gammes@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot des gammes');
        $restaurant->changeSiteOfficiel('https://bistrot.test');
        $entityManager->persist($restaurant);
        $activite = new Activite();
        $activite->changeLabel('Escalade des gammes');
        $entityManager->persist($activite);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Traiteur événementiel des gammes');
        $entityManager->persist($service);
        // Assez d'activités pour déclencher la pagination (14 par page).
        for ($i = 1; $i <= 15; ++$i) {
            $autre = new Activite();
            $autre->changeLabel('Activité de pagination '.$i);
            $entityManager->persist($autre);
        }
        $entityManager->flush();
        $client->loginUser($user);

        // Les listes filtrées par gamme ne montrent que leur gamme.
        $client->request('GET', '/referentiel/restaurants');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Restaurants');
        self::assertSelectorTextContains('table', 'Bistrot des gammes');
        self::assertSelectorTextNotContains('table', 'Escalade des gammes');
        // La pagination d'une liste par gamme conserve le paramètre {gamme}.
        $crawler = $client->request('GET', '/referentiel/activites');
        self::assertResponseIsSuccessful();
        $suivante = $crawler->filter('a:contains("Page suivante")');
        self::assertGreaterThan(0, $suivante->count(), 'La pagination doit apparaître avec 16 activités.');
        self::assertStringStartsWith('/referentiel/activites', (string) $suivante->attr('href'));
        $client->request('GET', (string) $suivante->attr('href'));
        self::assertResponseIsSuccessful();
        $client->request('GET', '/referentiel/services');
        self::assertSelectorTextContains('table', 'Traiteur événementiel des gammes');

        // Éditeur Restaurant : rail de sections, soumission partielle du label
        // sans toucher au reste (le site officiel, hors requête, doit survivre).
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bistrot des gammes');
        self::assertStringStartsWith('RES-', $crawler->filter('.page-description')->first()->text(null, true));
        // L'éditeur est la vue unique : un validateur y voit la suppression.
        self::assertSelectorExists('.danger-form');
        // Section Médias & menus : les documents existants du DAM sont intégrés.
        $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id().'?section=7');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Documents existants');
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id());
        $form = $crawler->selectButton('Enregistrer la section')->form();
        $values = $form->getPhpValues();
        $values['restaurant']['label'] = 'Bistrot renommé';
        // Soumission partielle stricte : seul le libellé voyage.
        unset($values['restaurant']['siteOfficiel'], $values['restaurant']['youtubeUrl']);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $entityManager->clear();
        $recharge = $entityManager->find(Restaurant::class, $restaurant->id());
        self::assertInstanceOf(Restaurant::class, $recharge);
        self::assertSame('Bistrot renommé', $recharge->label());
        self::assertSame('https://bistrot.test', $recharge->siteOfficiel());

        // Les éditeurs Activité et Service rendent avec leurs sections.
        $crawler = $client->request('GET', '/referentiel/activites/fiche/'.$activite->id());
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(7, $crawler->filter('nav[aria-label="Sections de la fiche"] li')->count());
        $client->request('GET', '/referentiel/services/fiche/'.$service->id().'?section=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'zone d\'intervention');
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_salle');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_acces');
        $this->connection->executeStatement('DELETE FROM pim_activite_offre');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
