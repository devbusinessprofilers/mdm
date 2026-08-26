<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class RechercheSuggestionsControllerTest extends WebTestCase
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

    public function testSuggereLesNomsDeFichesMotAMot(): void
    {
        $client = $this->clientAvecFiches();

        // Mots dans l'ordre du nom, mots-outils omis ou présents : même résultat.
        foreach (['jeu paume', 'jeu de la paume', 'Auberge Jeu'] as $saisie) {
            $client->request('GET', '/referentiel/suggestions', ['q' => $saisie]);
            self::assertResponseIsSuccessful();
            $data = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertContains('Auberge du Jeu de Paume', $data['suggestions'], sprintf('Saisie « %s »', $saisie));
            self::assertNotContains('Bistrot du Marché', $data['suggestions']);
        }
    }

    public function testInsensibleALaCasseEtAuxAccents(): void
    {
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'MARCHE']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Bistrot du Marché', $data['suggestions']);
    }

    public function testFauteSurLeDernierMotRetombeSurLesMotsComplets(): void
    {
        // « pomme » ne colle pas avec « auberge » : les auberges sortent quand
        // même (repli sur les mots complets, sans correction affichée).
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'auberge pomme']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Auberge du Jeu de Paume', $data['suggestions']);
    }

    public function testFauteMemeSurUnMotConnuAilleursTrouveLaFiche(): void
    {
        // « pomme » existe légitimement (« Hôtel La Pomme ») mais pas avec
        // « auberge » et « jeu » : la fiche visée doit quand même sortir.
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'auberge du jeu de pomme']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Auberge du Jeu de Paume', $data['suggestions']);
    }

    public function testDernierMotEnCoursDeFrappeRetombeSurLesMotsComplets(): void
    {
        // « pom » est un mot incomplet qui ne matche rien en l'état : les
        // mots déjà complets (« auberge », « jeu ») doivent porter la
        // suggestion.
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'auberge du jeu de pom']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Auberge du Jeu de Paume', $data['suggestions']);
        self::assertNull($data['correction']);
    }

    public function testCorrigeLaFauteSurLeDernierMotQuandCeNEstPasUnPrefixe(): void
    {
        // « pavilon » n'est le début d'aucun mot connu : ce n'est pas une
        // frappe en cours mais une faute — elle doit être corrigée plutôt que
        // de retomber sur les seuls « grand ».
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'grand pavilon']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Le Grand Pavillon Chantilly', $data['suggestions']);
        self::assertSame('grand pavillon', $data['correction']);
    }

    public function testCorrigeLaFauteEnMilieuDeRequete(): void
    {
        // Le repli sur les mots complets ne couvre pas une faute au milieu :
        // le correcteur prend le relais (« jau » → « jeu »).
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'auberge du jau de paume']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Auberge du Jeu de Paume', $data['suggestions']);
        self::assertSame('auberge du jeu de paume', $data['correction']);
    }

    public function testDernierMotEnCoursDeFrappeNonMassacre(): void
    {
        $client = $this->clientAvecFiches();

        // « pa » puis « pau » : préfixes de « paume », aucune correction parasite.
        $client->request('GET', '/referentiel/suggestions', ['q' => 'auberge pau']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains('Auberge du Jeu de Paume', $data['suggestions']);
        self::assertNull($data['correction']);
    }

    public function testSaisieTropCourteSansSuggestion(): void
    {
        $client = $this->clientAvecFiches();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'a']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame([], $data['suggestions']);
    }

    public function testAnonymeRedirigeVersLaConnexion(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $client->request('GET', '/referentiel/suggestions', ['q' => 'auberge']);
        self::assertResponseRedirects();
    }

    private function clientAvecFiches(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('suggestions@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        foreach (['Auberge du Jeu de Paume', 'Bistrot du Marché', 'Hôtel La Pomme', 'Le Grand Pavillon Chantilly'] as $label) {
            $lieu = new Lieu();
            $lieu->changeLabel($label);
            $entityManager->persist($lieu);
        }
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
