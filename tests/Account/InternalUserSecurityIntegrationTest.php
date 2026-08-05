<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\PasswordResetRequest;
use App\Account\Entity\User;
use App\Account\Repository\PasswordResetRequestRepository;
use App\Account\Service\PasswordResetTokenSigner;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class InternalUserSecurityIntegrationTest extends WebTestCase
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
            $this->cleanDatabase();
            $this->entityManager->clear();
        }
        parent::tearDown();
    }

    public function testForgottenPasswordUsesGenericResponseAndSingleUseReset(): void
    {
        $testId = strtolower((string) new Ulid());
        $ipHash = md5($testId);
        $ipAddress = sprintf('2001:db8:%s:%s:%s:%s::1', ...str_split(substr($ipHash, 0, 16), 4));
        $email = 'reset-'.$testId.'@example.com';
        $client = self::createClient([], ['REMOTE_ADDR' => $ipAddress]);
        $this->bootDatabaseServices();
        $user = $this->persistUser($email, 'Old-secure-password-2026!');

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $client->submit($crawler->selectButton('Recevoir un lien')->form([
            'forgot_password[email]' => $email,
        ]));
        self::assertResponseRedirects('/login');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account_password_reset_request'));

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $client->submit($crawler->selectButton('Recevoir un lien')->form([
            'forgot_password[email]' => 'unknown-'.$testId.'@example.com',
        ]));
        self::assertResponseRedirects('/login');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account_password_reset_request'));

        $this->entityManager->clear();
        $reset = self::getContainer()->get(PasswordResetRequestRepository::class)->findOneBy(['user' => $user->id()]);
        self::assertInstanceOf(PasswordResetRequest::class, $reset);
        $signature = self::getContainer()->get(PasswordResetTokenSigner::class)->sign($reset);
        $crawler = $client->request('GET', '/reinitialiser-mot-de-passe/'.$reset->id().'?signature='.$signature);
        self::assertResponseIsSuccessful();

        $plainPassword = 'New-unique-password-2026-Account!';
        $client->submit($crawler->selectButton('Enregistrer le mot de passe')->form([
            'new_password[password][first]' => $plainPassword,
            'new_password[password][second]' => $plainPassword,
        ]));
        self::assertResponseRedirects('/login');

        $this->entityManager->clear();
        $reset = self::getContainer()->get(PasswordResetRequestRepository::class)->find($reset->id());
        self::assertInstanceOf(PasswordResetRequest::class, $reset);
        self::assertNotNull($reset->usedAt());
        self::assertFalse($reset->isUsable());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($reset->user(), $plainPassword));

        $client->request('GET', '/reinitialiser-mot-de-passe/'.$reset->id().'?signature='.$signature);
        self::assertResponseStatusCodeSame(404);
    }

    public function testResetDoesNotReactivateDisabledUser(): void
    {
        self::createClient();
        $this->bootDatabaseServices();
        $user = $this->persistUser('disabled-reset@example.com', 'Old-secure-password-2026!');
        $user->deactivate();
        $this->entityManager->flush();

        $reset = self::getContainer()->get(\App\Account\Service\PasswordResetManager::class)->request($user);
        self::getContainer()->get(\App\Account\Service\PasswordResetManager::class)->reset($reset, 'New-secure-password-2026!');

        self::assertFalse($user->isActive());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'New-secure-password-2026!'));
    }

    public function testResetInvalidatesAnExistingSession(): void
    {
        $client = self::createClient();
        $this->bootDatabaseServices();
        $user = $this->persistUser('connected-reset@example.com', 'Old-secure-password-2026!');
        $client->loginUser($user);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $manager = self::getContainer()->get(\App\Account\Service\PasswordResetManager::class);
        $manager->reset($manager->request($user), 'New-secure-password-2026!');

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testSuperAdminCanResendInvitationAndAnonymizeInternalUser(): void
    {
        $client = self::createClient();
        $this->bootDatabaseServices();
        $admin = $this->persistUser('super-admin@example.com', 'Admin-secure-password-2026!', ['ROLE_SUPER_ADMIN']);
        $user = new User('pending-editor@example.com', ['ROLE_BP_EDITOR']);
        $user->deactivate();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/utilisateurs');
        $client->submit($crawler->selectButton('Renvoyer l’invitation')->form());
        self::assertResponseRedirects('/admin/utilisateurs');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account_invitation WHERE user_id = ?', [$user->id()]));

        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/admin/utilisateurs');

        $row = $this->connection->fetchAssociative('SELECT email, password, roles, is_active, deleted_at FROM account_user WHERE id = ?', [$user->id()]);
        self::assertIsArray($row);
        self::assertSame('deleted+'.strtolower($user->id()).'@invalid.local', $row['email']);
        self::assertNull($row['password']);
        self::assertSame('[]', $row['roles']);
        self::assertSame(0, (int) $row['is_active']);
        self::assertNotNull($row['deleted_at']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account_invitation WHERE user_id = ? AND invalidated_at IS NOT NULL', [$user->id()]));

        $replacement = new User('pending-editor@example.com', ['ROLE_BP_EDITOR']);
        $this->entityManager->persist($replacement);
        $this->entityManager->flush();
        self::assertNotSame($user->id(), $replacement->id());
    }

    /** @param list<string> $roles */
    private function persistUser(string $email, string $plainPassword, array $roles = []): User
    {
        $user = new User($email, $roles);
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
        $this->cleanDatabase();
    }

    private function cleanDatabase(): void
    {
        $this->connection->executeStatement('DELETE FROM account_password_reset_request');
        $this->connection->executeStatement('DELETE FROM account_invitation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
