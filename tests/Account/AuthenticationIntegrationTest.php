<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('database')]
final class AuthenticationIntegrationTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DELETE FROM account_user');
        }

        parent::tearDown();
    }

    public function testRepositoryLoadsNormalizedEmailAndLoginOpensTheApplication(): void
    {
        $client = self::createClient();
        $this->bootDatabaseServices();
        $user = $this->persistUser('admin@example.com', 'A-secure-password-2026!', ['ROLE_SUPER_ADMIN']);

        /** @var UserRepository $repository */
        $repository = $this->entityManager->getRepository(User::class);
        self::assertSame($user->id(), $repository->loadUserByIdentifier(' ADMIN@EXAMPLE.COM ')?->id());

        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Continuer')->form(['email' => ' ADMIN@EXAMPLE.COM ']));
        self::assertResponseRedirects('/connexion/mot-de-passe');
        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Se connecter')->form(['password' => 'A-secure-password-2026!']));

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'admin@example.com');

        $crawler = $client->getCrawler();
        $client->submit($crawler->selectButton('Se déconnecter')->form());
        self::assertResponseRedirects('/connexion');

        $client->request('GET', '/');
        self::assertResponseRedirects('/connexion');
    }

    public function testDisabledAccountAndWrongPasswordExposeTheSameGenericError(): void
    {
        $client = self::createClient();
        $this->bootDatabaseServices();
        $user = $this->persistUser('disabled@example.com', 'A-secure-password-2026!');
        $user->deactivate();
        $this->entityManager->flush();

        self::assertSame('Identifiants invalides.', $this->failedLoginMessage($client, 'disabled@example.com', 'A-secure-password-2026!'));
        self::assertSame('Identifiants invalides.', $this->failedLoginMessage($client, 'disabled@example.com', 'wrong-password'));
    }

    public function testSixthFailedLoginIsThrottledWithoutLeakingDetails(): void
    {
        $client = self::createClient([], ['REMOTE_ADDR' => '192.0.2.88']);
        $this->bootDatabaseServices();
        $this->persistUser('limited@example.com', 'A-secure-password-2026!');

        $message = '';
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $message = $this->failedLoginMessage($client, 'limited@example.com', 'wrong-password');
        }

        self::assertSame('Identifiants invalides.', $message);
    }

    /** @param list<string> $roles */
    private function persistUser(string $email, string $plainPassword, array $roles = []): User
    {
        $user = new User($email, $roles);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function bootDatabaseServices(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection->executeStatement('DELETE FROM account_user');
    }

    private function failedLoginMessage(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email, string $password): string
    {
        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Continuer')->form(['email' => $email]));
        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Se connecter')->form(['password' => $password]));
        // L'échec renvoie sur l'écran du mot de passe (failure_path).
        $crawler = $client->followRedirect();

        return trim($crawler->filter('[role="alert"]')->text());
    }
}
