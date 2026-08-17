<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class EspaceTravailControllerTest extends WebTestCase
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

    public function testCompteursEtPrioritesRapportesAMesFichesAssignees(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('espace@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $autre = new User('autre@example.test', ['ROLE_BP_VALIDATOR']);
        $autre->setPassword('not-used-by-login-user');
        $entityManager->persist($autre);

        $prioritaire = new Lieu();
        $prioritaire->changeLabel('Château prioritaire');
        $prioritaire->fiche()->changeAssignee($user);
        $entityManager->persist($prioritaire);

        $enAttente = new Lieu();
        $enAttente->changeLabel('Manoir en attente');
        $enAttente->fiche()->submitForValidation('editor');
        $enAttente->fiche()->changeAssignee($user);
        $entityManager->persist($enAttente);

        $horsPerimetre = new Lieu();
        $horsPerimetre->changeLabel('Domaine des autres');
        $horsPerimetre->fiche()->changeAssignee($autre);
        $entityManager->persist($horsPerimetre);

        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-de-travail');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour');

        $texte = $crawler->text(null, true);
        self::assertStringContainsString('dont 2 sous 50 % de complétude', $texte);
        self::assertSelectorTextContains('table', 'Château prioritaire');
        self::assertSelectorTextContains('table', 'Manoir en attente');
        self::assertSelectorTextNotContains('table', 'Domaine des autres');
        self::assertStringContainsString('Ouvrir', $texte);

        // Les compteurs : assignées, à compléter, en attente, suggestions.
        $cartes = $crawler->filter('.card-grid .card .stat-value');
        self::assertSame('2', trim($cartes->eq(0)->text()));
        self::assertSame('2', trim($cartes->eq(1)->text()));
        self::assertSame('1', trim($cartes->eq(2)->text()));
        self::assertSame('0', trim($cartes->eq(3)->text()));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_saved_view');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
