<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheCollaborateursEcranTest extends WebTestCase
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

    public function testInvitationEditionEtRetraitDepuisLaFiche(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('collab-fiche@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château des collaborateurs');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $id = (string) $lieu->id();
        $client->loginUser($user);

        // Invitation depuis la section 13.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=13');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Ajouter un collaborateur');
        $form = $crawler->selectButton('Inviter')->form();
        $values = $form->getPhpValues();
        $values['collab_invitation']['email'] = 'camille@exemple.test';
        $values['collab_invitation']['firstName'] = 'Camille';
        $values['collab_invitation']['lastName'] = 'Berthier';
        $values['collab_invitation']['phone'] = '+33 3 44 62 37 37';
        $values['collab_invitation']['role'] = 'manager';
        $values['collab_invitation']['receivesRequests'] = '1';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('body', 'camille@exemple.test');

        $affiliations = $entityManager->getRepository(FicheAffiliation::class)->findBy(['fiche' => $lieu->fiche()->id()]);
        self::assertCount(1, $affiliations);
        self::assertSame(FicheAffiliationRole::Manager, $affiliations[0]->role());
        self::assertTrue($affiliations[0]->receivesRequests());
        self::assertSame('+33 3 44 62 37 37', $affiliations[0]->collaborateur()->phone());

        // Édition : rôle utilisateur + contact de repli. Cibler le formulaire par
        // son nom — selectButton('Enregistrer') attraperait « Enregistrer la
        // diffusion » (Booster ma visibilité précède désormais Collaborateurs).
        $nom = 'collab_edition_'.$affiliations[0]->idString();
        $form = $crawler->filter('form[name="'.$nom.'"] button[type="submit"], form[name="'.$nom.'"] button:not([type])')->first()->form();
        $values = $form->getPhpValues();
        $values[$nom]['role'] = 'utilisateur';
        $values[$nom]['repli'] = '1';
        unset($values[$nom]['receivesRequests']);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        $entityManager->clear();
        $modifiee = $entityManager->getRepository(FicheAffiliation::class)->find($affiliations[0]->id());
        self::assertInstanceOf(FicheAffiliation::class, $modifiee);
        self::assertSame(FicheAffiliationRole::Utilisateur, $modifiee->role());
        self::assertFalse($modifiee->receivesRequests());
        self::assertTrue($modifiee->repli());

        // Retrait.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=13');
        $form = $crawler->selectButton('Retirer')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSame(0, $entityManager->getRepository(FicheAffiliation::class)->count(['fiche' => $lieu->fiche()->id()]));
        self::assertSelectorTextContains('body', 'Collaborateur retiré de la fiche.');
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_acces_lieu');
        $this->connection->executeStatement('DELETE FROM pim_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
