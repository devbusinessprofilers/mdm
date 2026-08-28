<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Dashboard\Entity\DashboardSnapshot;
use App\Dashboard\Service\DashboardStatsCalculator;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TableauDeBordControllerTest extends WebTestCase
{
    private Connection $connection;

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/connexion');
    }

    #[Group('database')]
    public function testLesFilesATraiterComptentLesFichesEnAttenteEtValidees(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('tableau-de-bord@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $aValider = new Lieu();
        $aValider->changeLabel('Fiche en attente');
        $aValider->fiche()->submitForValidation('editor');
        $entityManager->persist($aValider);

        $aPublier = new Lieu();
        $aPublier->changeLabel('Fiche validée');
        $aPublier->fiche()->submitForValidation('editor');
        $aPublier->fiche()->validate('validator');
        $entityManager->persist($aPublier);

        $publiee = new Lieu();
        $publiee->changeLabel('Fiche publiée récemment');
        $publiee->fiche()->publishForImport();
        $entityManager->persist($publiee);

        // Un champ à traduire sur la publiée : nourrit la carte « Caractères
        // non traduits » et les tuiles par langue via le snapshot.
        $entityManager->persist(new FicheTranslation($publiee->fiche(), 'nom', 'Nom', SupportedLocale::En, 'Fiche publiée récemment'));
        $entityManager->flush();

        $snapshot = new DashboardSnapshot((new DashboardStatsCalculator($entityManager))->compute(), 12);
        $entityManager->persist($snapshot);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        // Le gabarit maquette salue l'utilisateur connecté.
        self::assertSelectorTextContains('h1', 'Bonjour');

        // L'alias de la maquette sert le même écran.
        $client->request('GET', '/tableau-de-bord');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour');

        $crawler = $client->request('GET', '/');
        // Zone « À traiter » : une carte par file, volume affiché.
        $zoneFiles = $crawler->filter('[data-tableau-de-bord-target="zone"]')->first();
        self::assertStringContainsString('Fiches à valider', $zoneFiles->text(null, true));
        self::assertStringContainsString('1', $zoneFiles->text(null, true));
        self::assertStringContainsString('Fiches à publier', $zoneFiles->text(null, true));

        // Carte traductions : caractères du champ EN en attente, lien /outils.
        self::assertStringContainsString('Caractères non traduits', $zoneFiles->text(null, true));
        self::assertStringContainsString('1 champ · 1 fiche publiée', $zoneFiles->text(null, true));
        self::assertSame(1, $crawler->filter('a[href="/outils?famille=traduction"]')->count());

        // Zone Santé : tuiles par langue et bascule Traductions du croisement.
        $main = $crawler->filter('main')->text(null, true);
        self::assertStringContainsString('Traductions des fiches publiées', $main);
        self::assertStringContainsString('Anglais', $main);
        self::assertStringContainsString('1 à traduire', $main);
        self::assertStringContainsString('Néerlandais', $main);
        self::assertStringContainsString('Champs traduits par gamme', $main);

        // Dernières publications, la plus récente d'abord, avec lien vers l'éditeur.
        self::assertSelectorTextContains('main', 'Dernières publications');
        self::assertStringContainsString('Fiche publiée récemment', $crawler->filter('main')->text(null, true));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM enrichment_fiche_translation');
        $this->connection->executeStatement('DELETE FROM dashboard_snapshot');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
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
}
