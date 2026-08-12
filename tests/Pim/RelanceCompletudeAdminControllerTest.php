<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\FicheRelancePlanifiee;
use App\Pim\Entity\Lieu\Lieu;
use App\Shared\Entity\Parametre;
use App\Shared\Enum\TypeParametre;
use App\Shared\Repository\ParametreRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class RelanceCompletudeAdminControllerTest extends WebTestCase
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
            foreach ([
                'pim_fiche_relance_planifiee',
                'pim_fiche_relance',
                'pim_ressource_lieu',
                'pim_fiche_attribute_value',
                'pim_lieu_administratif',
                'pim_lieu_tarification',
                'pim_lieu',
                'pim_fiche',
                'pim_localisation',
            ] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
            $this->connection->executeStatement('DELETE FROM account_user');
        }
        parent::tearDown();
    }

    public function testLaPageEstReserveeAuxAdmins(): void
    {
        $client = self::createClient();

        $client->loginUser($this->persistUser('editor-relances@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/relances-completude');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($this->persistUser('admin-relances@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/relances-completude');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Relances de complétude');
    }

    public function testExclureUneLigneDuLot(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $lieu = new Lieu();
        $lieu->changeLabel('Château à exclure');
        $entityManager->persist($lieu);
        $planifiee = new FicheRelancePlanifiee($lieu->fiche(), new \DateTimeImmutable(), 30, ['presta@example.test']);
        $entityManager->persist($planifiee);
        $entityManager->flush();

        $client->loginUser($this->persistUser('admin-relances@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/relances-completude');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.data-table', 'Château à exclure');

        $client->submitForm('Exclure');
        self::assertResponseRedirects('/admin/relances-completude');
        self::assertSame(
            'exclue',
            $this->connection->fetchOne('SELECT statut FROM pim_fiche_relance_planifiee WHERE id = ?', [Ulid::fromString($planifiee->id())->toBinary()]),
        );
    }

    public function testBasculerLEnvoiAutomatique(): void
    {
        $client = self::createClient();
        $this->parametreEnvoiAuto();

        $client->loginUser($this->persistUser('admin-relances@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/relances-completude');
        self::assertResponseIsSuccessful();

        // Défaut env : envoi actif, le bouton désactive et surcharge en base.
        $client->submitForm('Désactiver l’envoi automatique');
        self::assertResponseRedirects('/admin/relances-completude');
        self::assertSame(
            '0',
            $this->connection->fetchOne("SELECT valeur FROM parametre WHERE nom = 'completude.rappel_auto_actif'"),
        );
    }

    /** Charge le paramètre du catalogue, en le créant si les seeds n'ont pas été joués. */
    private function parametreEnvoiAuto(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(ParametreRepository::class);
        if (!$repository->parNom('completude.rappel_auto_actif') instanceof Parametre) {
            $entityManager->persist(new Parametre('completude.rappel_auto_actif', 'Paramètre de test.', TypeParametre::Booleen));
            $entityManager->flush();
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
