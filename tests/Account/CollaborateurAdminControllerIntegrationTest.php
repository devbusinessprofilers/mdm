<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheAffiliationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class CollaborateurAdminControllerIntegrationTest extends WebTestCase
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
            $this->connection->executeStatement('DELETE FROM outbox_message');
            $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
            $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
            $this->connection->executeStatement('DELETE FROM account_user');
        }
        parent::tearDown();
    }

    public function testSuperAdminCanListCollaborateursNominateManagerAndDisableAccount(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM account_user');

        $admin = new User('super-admin@example.com', ['ROLE_SUPER_ADMIN']);
        $admin->setPassword('test-password-hash');
        $provider = new FicheCollaborateur('provider@example.com', 'Grace', 'Hopper');
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->changeLabel('Lieu administré');
        $this->entityManager->persist($admin);
        $this->entityManager->persist($provider);
        $this->entityManager->persist($fiche);
        $this->entityManager->flush();
        $client->loginUser($admin);

        $client->request('GET', '/admin/collaborateurs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'provider@example.com');

        $crawler = $client->request('GET', '/admin/collaborateurs/'.$provider->id());
        self::assertResponseIsSuccessful();
        $ficheField = $crawler->filter('select[name="invitation_affiliation[fiche]"]');
        self::assertCount(1, $ficheField);
        $autocompleteUrl = (string) $ficheField->attr('data-symfony--ux-autocomplete--autocomplete-url-value');
        self::assertStringContainsString('/autocomplete/fiche_autocomplete_type', $autocompleteUrl);
        self::assertCount(0, $ficheField->filter(sprintf('option[value="%s"]', $fiche->idString())));

        $client->request('GET', $autocompleteUrl.'?query=administré');
        self::assertResponseIsSuccessful();
        /** @var array{results: list<array{value: string, text: string}>} $autocompletePayload */
        $autocompletePayload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([['value' => $fiche->idString(), 'text' => 'Lieu administré']], $autocompletePayload['results']);

        $crawler = $client->request('GET', '/admin/collaborateurs/'.$provider->id());
        $affiliationForm = $crawler->selectButton('Ajouter')->form();
        $affiliationForm->disableValidation();
        $affiliationForm->setValues([
            'invitation_affiliation[fiche]' => $fiche->idString(),
            'invitation_affiliation[role]' => FicheAffiliationRole::Manager->value,
            'invitation_affiliation[receivesRequests]' => '1',
        ]);
        $client->submit($affiliationForm);
        self::assertResponseRedirects('/admin/collaborateurs/'.$provider->id());
        $affiliation = self::getContainer()->get(FicheAffiliationRepository::class)->findFor($provider, $fiche);
        self::assertNotNull($affiliation);
        self::assertSame(FicheAffiliationRole::Manager, $affiliation->role());
        self::assertTrue($affiliation->receivesRequests());

        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Désactiver')->form());
        self::assertResponseRedirects('/admin/collaborateurs/'.$provider->id());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT is_active FROM pim_fiche_collaborateur WHERE email = ?', [$provider->email()]));
    }
}
