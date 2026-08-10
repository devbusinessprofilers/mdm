<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class PasswordChangeControllerTest extends WebTestCase
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

    public function testMotDePasseForceRedirigeToutVersLEcranDeChangement(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('force@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $user->requirePasswordChange();
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        // Toute navigation est détournée vers l'écran de changement.
        $client->request('GET', '/referentiel');
        self::assertResponseRedirects('/changer-mon-mot-de-passe');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Changer mon mot de passe');
        self::assertSelectorTextContains('body', 'défini pour vous');

        // Le changement lève l'obligation : la navigation redevient libre.
        $form = $crawler->selectButton('Changer mon mot de passe')->form([
            'new_password[password][first]' => 'nouveau-mot-de-passe-solide',
            'new_password[password][second]' => 'nouveau-mot-de-passe-solide',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();

        $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        self::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT must_change_password FROM account_user WHERE email = ?', ['force@example.test']),
        );
    }

    public function testEcranAccessibleSansObligation(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('libre@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/changer-mon-mot-de-passe');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'au moins 12 caractères');
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM account_password_reset_request');
        $this->connection->executeStatement('DELETE FROM account_invitation');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
