<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class CollaborateurConsultationControllerTest extends WebTestCase
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
            $this->entityManager->clear();
            $this->purge();
        }
        parent::tearDown();
    }

    private function purge(): void
    {
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM account_user');
    }

    public function testValidateurConsulteLesUtilisateursEtLeursFiches(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->purge();

        $validateur = new User('validateur@example.com', ['ROLE_BP_VALIDATOR']);
        $validateur->setPassword('test-password-hash');
        $provider = new FicheCollaborateur('provider@example.com', 'Grace', 'Hopper');
        $autre = new FicheCollaborateur('autre@example.com', 'Alan', 'Turing');
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->changeLabel('Lieu consulté');
        $affiliation = new FicheAffiliation($provider, $fiche, FicheAffiliationRole::Manager, $validateur);
        foreach ([$validateur, $provider, $autre, $fiche, $affiliation] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $client->loginUser($validateur);

        $crawler = $client->request('GET', '/utilisateurs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Utilisateurs');
        self::assertSelectorTextContains('main', 'provider@example.com');
        self::assertSelectorTextContains('main', 'Lieu consulté');
        self::assertSelectorTextContains('main', 'Manager');
        self::assertSelectorTextContains('main', 'Actif');
        $lienFiche = $crawler->filter(sprintf('a[href="/referentiel/lieux/fiche/%s"]', $fiche->idString()));
        self::assertCount(1, $lienFiche);
        // Pas de pont vers la page de gestion super admin sur cette page.
        self::assertSelectorNotExists('a[href="/admin/collaborateurs"]');

        // La recherche filtre par nom ou email.
        $client->request('GET', '/utilisateurs?q=turing');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'autre@example.com');
        self::assertSelectorTextNotContains('main', 'provider@example.com');
        self::assertSelectorTextContains('main', 'Page 1 / 1 — 1 utilisateur');
    }

    public function testValidateurInviteUnNouvelUtilisateur(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->purge();

        $validateur = new User('validateur@example.com', ['ROLE_BP_VALIDATOR']);
        $validateur->setPassword('test-password-hash');
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->changeLabel('Lieu invité');
        $this->entityManager->persist($validateur);
        $this->entityManager->persist($fiche);
        $this->entityManager->flush();
        $client->loginUser($validateur);

        $crawler = $client->request('GET', '/utilisateurs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Inviter un utilisateur');

        $form = $crawler->selectButton('Inviter')->form();
        $form->disableValidation();
        $form->setValues([
            'invitation_collaborateur[email]' => 'nouveau@example.com',
            'invitation_collaborateur[firstName]' => 'Ada',
            'invitation_collaborateur[lastName]' => 'Lovelace',
            'invitation_collaborateur[language]' => 'fr',
            'invitation_collaborateur[fiche]' => $fiche->idString(),
            'invitation_collaborateur[role]' => FicheAffiliationRole::Manager->value,
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/utilisateurs');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('body', 'Utilisateur invité et affilié.');
        self::assertSelectorTextContains('main', 'nouveau@example.com');
        self::assertSelectorTextContains('main', 'Lieu invité');
    }

    public function testEditeurRefuse(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->purge();

        $editeur = new User('editeur@example.com', ['ROLE_BP_EDITOR']);
        $editeur->setPassword('test-password-hash');
        $this->entityManager->persist($editeur);
        $this->entityManager->flush();

        $client->loginUser($editeur);
        $client->request('GET', '/utilisateurs');
        self::assertResponseStatusCodeSame(403);
    }
}
