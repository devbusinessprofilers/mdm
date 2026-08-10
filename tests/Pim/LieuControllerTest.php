<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\StatutFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class LieuControllerTest extends WebTestCase
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
            $this->connection->executeStatement('DELETE FROM pim_fiche_search');
            $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
            $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
            $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
            $this->connection->executeStatement('DELETE FROM pim_lieu');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
            $this->connection->executeStatement('DELETE FROM pim_localisation');
            $this->connection->executeStatement('DELETE FROM outbox_message');
            $this->connection->executeStatement('DELETE FROM account_user');
        }

        parent::tearDown();
    }

    public function testBpUserCanCreateAndListALieu(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');

        $user = new User('crud-lieu@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/referentiel/fiche/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nouvelle fiche');

        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Lieu CRUD temporaire',
            'fiche_creation[lieuTypologie]' => ['GENERALE_TYPOLOGIE_20'],
            'fiche_creation[localisation][pays]' => 'France',
            'fiche_creation[localisation][codePostal]' => '75001',
            'fiche_creation[localisation][ville]' => 'Paris',
        ]);

        self::assertResponseRedirects();
        self::assertSame(1, $entityManager->getRepository(Lieu::class)->count([]));

        // La liste vit désormais sur le référentiel MDM.
        $client->request('GET', '/referentiel/lieux');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Lieu CRUD temporaire');
    }

    public function testBpUserCanSearchLieuxAndKeepCriteriaInPagination(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');

        $user = new User('search-lieu@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $paris = $this->createSearchableLieu($entityManager, 201, 'Palais Lumière Paris', 'Paris', StatutFiche::Publiee);
        $this->createSearchableLieu($entityManager, 202, 'Palais Lumière Lyon', 'Lyon', StatutFiche::EnCours);
        $entityManager->flush();
        $client->loginUser($user);

        // La recherche vit désormais sur la liste MDM : plein-texte + facettes.
        $client->request('GET', '/referentiel/lieux', ['f' => ['q' => 'pal pari', 'statuts' => ['publiee']]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 fiche dans le filtre courant');
        self::assertSelectorTextContains('table', $paris->label() ?? '');
        self::assertSelectorTextNotContains('table', 'Palais Lumière Lyon');

        $client->request('GET', '/referentiel/lieux', ['f' => ['q' => 'palais']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '2 fiches dans le filtre courant');

        $client->request('GET', '/referentiel/lieux', ['f' => ['q' => 'introuvable']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Aucune fiche ne correspond à ces filtres');

        $client->request('GET', '/referentiel/lieux?cursor=invalid');
        self::assertResponseStatusCodeSame(404);
    }

    private function createSearchableLieu(
        EntityManagerInterface $entityManager,
        int $code,
        string $label,
        string $ville,
        StatutFiche $status,
    ): Lieu {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $localisation = new Localisation();
        $localisation->changeVille($ville);
        $localisation->changePays('France');
        $lieu->changeLocalisation($localisation);
        if (StatutFiche::Publiee === $status) {
            $lieu->fiche()->publishForImport();
        }
        $entityManager->persist($lieu);
        $entityManager->persist(new FicheSearchDocument(
            $lieu->fiche(),
            implode(' ', [$code, $label, $ville, 'France']),
            $lieu->fiche()->version(),
        ));

        return $lieu;
    }
}
