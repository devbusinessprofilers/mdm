<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class GlobalSearchControllerTest extends WebTestCase
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

    public function testAnonymousAndBasicUsersCannotAccessSearch(): void
    {
        $client = self::createClient();
        $client->request('GET', '/recherche');
        self::assertResponseRedirects('http://localhost/connexion');

        $client->loginUser($this->persistUser('basic-search@example.test'));
        $client->request('GET', '/recherche');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorSeesEmptyPageAndHeaderSearch(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('editor-search@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/recherche');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Recherche globale');
        self::assertSelectorExists('header form.header-search[action="/recherche"] input[name="q"]');
        self::assertSelectorTextContains('main', 'Saisissez un code');
        self::assertSelectorNotExists('main table');
    }

    public function testEmptyLimitIsAccepted(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('empty-limit-search@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/recherche?limit=&q=&status=&submit=&type=lieu');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="limit"]');
    }

    public function testInvalidPublicParametersReturnBadRequest(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('invalid-search@example.test', ['ROLE_BP_EDITOR']));

        foreach ([
            'type=traiteur',
            'status=inconnu',
            'limit=0',
            'limit=101',
            'cursor=invalide',
            'country=ZZ',
            'completeness_min=-1',
            'completeness_max=101',
            'q=test&completeness_min=60&completeness_max=40',
        ] as $query) {
            $client->request('GET', '/recherche?'.$query);
            self::assertResponseStatusCodeSame(400, $query);
        }
    }

    public function testCountryAndCompletenessFiltersAreExposed(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('filters-search@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/recherche?q=test&completeness_min=10&completeness_max=90');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main select[name="country"]');
        self::assertSelectorExists('main input[name="completeness_min"]');
        self::assertSelectorExists('main input[name="completeness_max"]');
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
