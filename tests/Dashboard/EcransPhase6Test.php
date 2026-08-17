<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Écrans de la phase 6 : /outils, /medias, /qualite. */
#[Group('database')]
final class EcransPhase6Test extends WebTestCase
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

    public function testLesTroisEcransRendentEtLesOngletsRepondent(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('phase6@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château phase 6');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        // /outils : journal vide mais rendu, filtres présents.
        $client->request('GET', '/outils');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Journal des traitements');
        $client->request('GET', '/outils', ['famille' => 'import', 'erreurs' => 1]);
        self::assertResponseIsSuccessful();

        // /medias : bibliothèque (contenu DAM réutilisé) puis onglets propres.
        $client->request('GET', '/medias');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Médias');
        foreach (['doublons', 'droits', 'sync', 'formats', 'import', 'ia', 'meta'] as $onglet) {
            $client->request('GET', '/medias', ['onglet' => $onglet]);
            self::assertResponseIsSuccessful();
        }

        // /qualite : les cinq onglets rendent, la santé liste les gammes.
        $crawler = $client->request('GET', '/qualite');
        self::assertResponseIsSuccessful();
        // Le h1 porte l'onglet actif ; « Gouvernance des données » titre le rail.
        self::assertSelectorTextContains('h1', 'Santé des données');
        self::assertSelectorTextContains('body', 'Gouvernance des données');
        self::assertSelectorTextContains('table', 'Lieux');
        foreach (['conflits', 'formes', 'notifs', 'decisions'] as $onglet) {
            $client->request('GET', '/qualite', ['onglet' => $onglet]);
            self::assertResponseIsSuccessful();
        }
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
