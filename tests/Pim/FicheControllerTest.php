<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheControllerTest extends WebTestCase
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
            $this->connection->executeStatement('DELETE FROM outbox_message');
            $this->connection->executeStatement('DELETE FROM account_user');
        }

        parent::tearDown();
    }

    public function testAnonymousAndBasicUsersCannotAccessList(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/fiches');
        self::assertResponseRedirects('http://localhost/login');

        $client->loginUser($this->persistUser('basic-fiches@example.test'));
        $client->request('GET', '/admin/fiches');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorSeesListAndMenuEntry(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('editor-fiches@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/fiches');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Toutes les fiches');
        self::assertSelectorExists('header nav a[href="/admin/fiches"]');
        self::assertSelectorExists('main select[name="type"]');
        self::assertSelectorExists('main select[name="status"]');
        self::assertSelectorExists('main select[name="country"]');
        self::assertSelectorExists('main input[name="completeness_min"]');
        self::assertSelectorExists('main input[name="completeness_max"]');
        self::assertSelectorExists('main table');
    }

    public function testInvalidPublicParametersReturnBadRequest(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('invalid-fiches@example.test', ['ROLE_BP_EDITOR']));

        foreach ([
            'type=traiteur',
            'status=inconnu',
            'limit=0',
            'limit=101',
            'cursor=invalide',
            'country=ZZ',
            'completeness_min=200',
            'completeness_max=-5',
            'completeness_min=60&completeness_max=40',
        ] as $query) {
            $client->request('GET', '/admin/fiches?'.$query);
            self::assertResponseStatusCodeSame(400, $query);
        }
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
}
