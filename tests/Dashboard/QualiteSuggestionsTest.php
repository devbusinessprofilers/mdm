<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Enum\SuggestionStatut;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Tableau de suggestions de l'écran Qualité : onglets par source + arbitrage groupé. */
#[Group('database')]
final class QualiteSuggestionsTest extends WebTestCase
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
            $this->connection->executeStatement('DELETE FROM pim_fiche_suggestion');
            $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
            $this->connection->executeStatement('DELETE FROM pim_lieu');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
            $this->connection->executeStatement('DELETE FROM account_user');
        }
        parent::tearDown();
    }

    public function testOngletSourceEtAcceptationGroupee(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('qualite-sug@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('x');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel de la Suggestion');
        $entityManager->persist($lieu);
        $suggestion = new FicheSuggestion(
            $lieu->fiche(),
            SuggestionSource::Wikidata,
            SuggestionAction::RemplirChamp,
            'lieu_chaine',
            'Chaîne / groupe hôtelier',
            null,
            'Accor',
        );
        $entityManager->persist($suggestion);
        $entityManager->flush();
        $suggestionId = $suggestion->id();
        $lieuId = $lieu->id();
        $client->loginUser($user);

        // L'onglet Wikidata (« Chaîne hôtelière ») liste la proposition, source affichée en provenance.
        $crawler = $client->request('GET', '/qualite', ['onglet' => 'conflits', 'src' => 'wikidata']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Accor');
        self::assertSelectorTextContains('body', 'Chaîne hôtelière (1)');
        self::assertSelectorTextContains('body', 'Wikidata');

        // Acceptation groupée de la ligne cochée.
        $token = $crawler->filter('input[name="suggestion_selection[_token]"]')->attr('value');
        $client->request('POST', '/qualite/suggestions/accepter?src=wikidata&page=1', [
            'suggestion_selection' => ['ids' => ['suggestion:'.$suggestionId], '_token' => $token],
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 suggestion(s) appliquée(s).');

        $entityManager->clear();
        $lieu = $entityManager->find(Lieu::class, $lieuId);
        self::assertNotNull($lieu);
        self::assertSame('Accor', $lieu->chaineHoteliere());
        $suggestion = $entityManager->find(FicheSuggestion::class, $suggestionId);
        self::assertNotNull($suggestion);
        self::assertSame(SuggestionStatut::Acceptee, $suggestion->statut());
    }
}
