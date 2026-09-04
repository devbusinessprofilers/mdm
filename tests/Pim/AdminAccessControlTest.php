<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Verrouillage du durcissement : /admin est réservé à ROLE_ADMIN, les flux métier déplacés gardent leur garde propre. */
#[Group('database')]
final class AdminAccessControlTest extends WebTestCase
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

    public function testLesEcransAdminSontReservesAuxAdmins(): void
    {
        $client = self::createClient();

        $client->loginUser($this->persistUser('editor-acl@example.test', ['ROLE_BP_EDITOR']));
        foreach (['/admin', '/admin/tableau-de-bord', '/admin/dam'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path.' doit être refusé à un éditeur');
        }

        $client->loginUser($this->persistUser('validator-acl@example.test', ['ROLE_BP_VALIDATOR']));
        foreach (['/admin', '/admin/tableau-de-bord', '/admin/dam'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path.' doit être refusé à un validateur');
        }

        $client->loginUser($this->persistUser('admin-acl@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    public function testLesPhotosDeLieuExigentUnRoleEditeur(): void
    {
        $client = self::createClient();
        $user = $this->persistUser('basic-acl@example.test');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu verrouillé');
        $entityManager->persist($lieu);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('POST', '/referentiel/lieux/fiche/'.$lieu->id().'/photos');
        self::assertResponseStatusCodeSame(403);
    }

    /** @param list<string> $roles */
    private function persistUser(string $email, array $roles = []): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM account_user');

        $user = new User($email, $roles);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
