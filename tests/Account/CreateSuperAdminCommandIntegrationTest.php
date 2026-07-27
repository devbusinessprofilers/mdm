<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('database')]
final class CreateSuperAdminCommandIntegrationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection->executeStatement('DELETE FROM account_user');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DELETE FROM account_user');
        }

        parent::tearDown();
    }

    public function testCommandCreatesNormalizedSuperAdminAndIsIdempotent(): void
    {
        $tester = $this->commandTester();
        $tester->setInputs(['A-secure-password-2026!', 'A-secure-password-2026!']);

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => ' ADMIN@Example.COM ']));

        /** @var UserRepository $repository */
        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findOneByEmail('admin@example.com');
        self::assertNotNull($user);
        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertTrue($user->isActive());
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'A-secure-password-2026!'));

        self::assertSame(Command::SUCCESS, $this->commandTester()->execute(['email' => 'admin@example.com']));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account_user'));
    }

    public function testCommandDoesNotPromoteAnExistingRegularAccount(): void
    {
        $user = new User('member@example.com');
        $user->setPassword('existing-hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $tester = $this->commandTester();
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'MEMBER@example.com']));

        $this->entityManager->clear();
        /** @var UserRepository $repository */
        $repository = $this->entityManager->getRepository(User::class);
        self::assertSame(['ROLE_USER'], $repository->findOneByEmail('member@example.com')?->getRoles());
    }

    public function testCommandRejectsMismatchedPasswords(): void
    {
        $tester = $this->commandTester();
        $tester->setInputs(['A-secure-password-2026!', 'A-different-password-2026!']);

        self::assertSame(Command::INVALID, $tester->execute(['email' => 'new@example.com']));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account_user'));
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        if (null === $kernel) {
            throw new \LogicException('The test kernel is not booted.');
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find('app:user:create-super-admin'));
    }
}
