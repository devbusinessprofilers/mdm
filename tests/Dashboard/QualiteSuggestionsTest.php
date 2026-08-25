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
            $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
            $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
            $this->connection->executeStatement('DELETE FROM pim_lieu');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
            $this->connection->executeStatement('DELETE FROM account_user');
            // Valeur LOV créée par l'accept d'une chaîne hors liste (les
            // traductions suivent par cascade).
            $this->connection->executeStatement("DELETE FROM pim_attribute_value WHERE code = 'GENERALE_CHAINES_GROUPE_HOT_ACCOR'");
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

        // La pastille « Conflits » du rail compte aussi les suggestions génériques.
        $badges = self::getContainer()->get(\App\Dashboard\Repository\QualiteRepository::class)->badges();
        self::assertGreaterThanOrEqual(1, $badges['conflits']);

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
        // « Accor » n'est pas dans la liste : l'accept crée la valeur LOV puis
        // la coche — le sélecteur de l'éditeur est l'unique champ chaîne.
        self::assertContains('GENERALE_CHAINES_GROUPE_HOT_ACCOR', $lieu->generaleChainesGroupeHot());
        self::assertSame('Accor', $this->connection->fetchOne("SELECT label FROM pim_attribute_value WHERE code = 'GENERALE_CHAINES_GROUPE_HOT_ACCOR'"));
        $suggestion = $entityManager->find(FicheSuggestion::class, $suggestionId);
        self::assertNotNull($suggestion);
        self::assertSame(SuggestionStatut::Acceptee, $suggestion->statut());
    }

    public function testSuggestionPerimeeResteEnAttenteSansBloquerLeLot(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('qualite-sug2@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('x');
        $entityManager->persist($user);

        // Description saisie à la main après le scan (valeurActuelle = null au
        // scan) : la suggestion est périmée, la saisie ne doit pas être écrasée.
        $lieuPerime = new Lieu();
        $lieuPerime->changeLabel('Hôtel Déjà Renseigné');
        $lieuPerime->changeDescGenerale('Décrit à la main après le scan.');
        $entityManager->persist($lieuPerime);
        $perimee = new FicheSuggestion(
            $lieuPerime->fiche(),
            SuggestionSource::Wikidata,
            SuggestionAction::RemplirChamp,
            'lieu_desc_generale',
            'Description générale',
            null,
            'Aperçu proposé.',
            null,
            ['text' => 'Description proposée par la source.'],
        );
        $entityManager->persist($perimee);

        $lieuValide = new Lieu();
        $lieuValide->changeLabel('Hôtel Sans Description');
        $entityManager->persist($lieuValide);
        $valide = new FicheSuggestion(
            $lieuValide->fiche(),
            SuggestionSource::Wikidata,
            SuggestionAction::RemplirChamp,
            'lieu_desc_generale',
            'Description générale',
            null,
            'Aperçu proposé.',
            null,
            ['text' => 'Description proposée par la source.'],
        );
        $entityManager->persist($valide);
        $entityManager->flush();
        $perimeeId = $perimee->id();
        $valideId = $valide->id();
        $lieuPerimeId = $lieuPerime->id();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/qualite', ['onglet' => 'conflits', 'src' => 'wikidata']);
        $token = $crawler->filter('input[name="suggestion_selection[_token]"]')->attr('value');
        $client->request('POST', '/qualite/suggestions/accepter?src=wikidata&page=1', [
            'suggestion_selection' => ['ids' => ['suggestion:'.$perimeeId, 'suggestion:'.$valideId], '_token' => $token],
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 suggestion(s) appliquée(s), 1 en échec');

        $entityManager->clear();
        // La ligne périmée n'écrase pas la saisie et reste en attente : le flush
        // de la ligne suivante du lot ne doit pas persister un faux « acceptée ».
        $lieuPerime = $entityManager->find(Lieu::class, $lieuPerimeId);
        self::assertNotNull($lieuPerime);
        self::assertSame('Décrit à la main après le scan.', $lieuPerime->descGenerale());
        $perimee = $entityManager->find(FicheSuggestion::class, $perimeeId);
        self::assertNotNull($perimee);
        self::assertSame(SuggestionStatut::EnAttente, $perimee->statut());
        $valide = $entityManager->find(FicheSuggestion::class, $valideId);
        self::assertNotNull($valide);
        self::assertSame(SuggestionStatut::Acceptee, $valide->statut());
    }

    public function testUnePropositionIaVitDansLeTableauValeursIaPasDansLesOnglets(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('qualite-ia@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('x');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château décrit par IA');
        $entityManager->persist($lieu);
        $entityManager->persist(new FicheSuggestion(
            $lieu->fiche(),
            SuggestionSource::Ia,
            SuggestionAction::RemplirChamp,
            'lieu_desc_generale',
            'Description générale',
            null,
            'Un domaine au calme, à deux pas de la gare.',
        ));
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/qualite', ['onglet' => 'conflits']);
        self::assertResponseIsSuccessful();

        // La proposition IA figure dans « Valeurs suggérées par l'IA en
        // attente », avec « Ouvrir » vers la fiche (l'arbitrage y vit)…
        $tableauIa = $crawler->filter('section[aria-label="Suggestions IA à arbitrer"]');
        self::assertStringContainsString('Château décrit par IA', $tableauIa->text());
        self::assertStringContainsString('Description générale', $tableauIa->text());
        self::assertSame(1, $tableauIa->filter('a[href*="/referentiel/lieux/fiche/"]')->count());

        // …et pas d'onglet dédié dans le tableau des sources.
        self::assertSelectorTextNotContains('body', 'Descriptions IA');
    }
}
