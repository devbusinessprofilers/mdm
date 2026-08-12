<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Account\Entity\User;
use App\Shared\Entity\Parametre;
use App\Shared\Enum\TypeParametre;
use App\Shared\Repository\ParametreRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class ParametreAdminControllerTest extends WebTestCase
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
            $this->connection->executeStatement('UPDATE parametre SET valeur = NULL, updated_at = NULL');
            $this->connection->executeStatement('DELETE FROM account_user');
        }
        parent::tearDown();
    }

    public function testLaPageEstReserveeAuxAdmins(): void
    {
        $client = self::createClient();

        $client->loginUser($this->persistUser('editor-parametres@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/parametres');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($this->persistUser('admin-parametres@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/parametres');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Paramètres applicatifs');
    }

    public function testSurchargerPuisRevenirAuDefaut(): void
    {
        $client = self::createClient();
        $this->parametre('completude.seuil_rappel');
        $client->loginUser($this->persistUser('admin-parametres@example.test', ['ROLE_ADMIN']));

        // Surcharge : le formulaire d'édition écrit la valeur en base.
        $client->request('GET', '/admin/parametres/completude.seuil_rappel/modifier');
        self::assertResponseIsSuccessful();
        $client->submitForm('Enregistrer', ['parametre[valeur]' => '42']);
        self::assertResponseRedirects('/admin/parametres');
        self::assertSame(
            '42',
            $this->connection->fetchOne("SELECT valeur FROM parametre WHERE nom = 'completude.seuil_rappel'"),
        );

        // Retour au défaut depuis l'index (bouton POST + CSRF).
        $client->followRedirect();
        $client->submitForm('Revenir au défaut');
        self::assertResponseRedirects('/admin/parametres');
        self::assertNull(
            $this->connection->fetchOne("SELECT valeur FROM parametre WHERE nom = 'completude.seuil_rappel'"),
        );
    }

    public function testUnParametreInconnuRenvoie404(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('admin-parametres@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/parametres/inconnu.nexiste_pas/modifier');
        self::assertResponseStatusCodeSame(404);
    }

    /** Charge le paramètre du catalogue, en le créant si les seeds n'ont pas été joués. */
    private function parametre(string $nom): Parametre
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(ParametreRepository::class);
        $parametre = $repository->parNom($nom);
        if (!$parametre instanceof Parametre) {
            $parametre = new Parametre($nom, 'Paramètre de test.', TypeParametre::Entier);
            $entityManager->persist($parametre);
            $entityManager->flush();
        }

        return $parametre;
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
