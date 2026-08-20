<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TriReferentiel;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\ReadModel\ReferentielCursor;
use App\Pim\Service\FicheSearchIndexer;
use App\Pim\Service\ReferentielActionGroupee;
use App\Pim\Service\ReferentielListeProvider;
use Symfony\Component\Uid\Ulid;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class ReferentielControllerTest extends WebTestCase
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

    public function testListeFiltreParStatutEtCompteLesFacettesSurLesAutresGroupes(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieuPublie = new Lieu();
        $lieuPublie->changeLabel('Château publié');
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeCountryCode('FR');
        $localisation->changeVille('Paris');
        $lieuPublie->fiche()->changeLocalisation($localisation);
        $lieuPublie->fiche()->publishForImport();
        $entityManager->persist($lieuPublie);

        $lieuEnCours = new Lieu();
        $lieuEnCours->changeLabel('Château en cours');
        $entityManager->persist($lieuEnCours);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot publié');
        $restaurant->fiche()->publishForImport();
        $entityManager->persist($restaurant);

        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Toutes les fiches');
        self::assertSelectorTextContains('table', 'Château publié');
        self::assertSelectorTextContains('table', 'Bistrot publié');

        // Filtre statut = publiée : 2 lignes, et le compte du groupe statut
        // reste calculé sans son propre filtre (1 « en cours » visible).
        $crawler = $client->request('GET', '/referentiel', ['f' => ['statuts' => ['publiee']]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Château publié');
        self::assertSelectorTextNotContains('table', 'Château en cours');
        self::assertStringContainsString('2 dans le filtre', $crawler->text(null, true));

        // Intersection entre groupes : statut publiée + gamme restaurant.
        $client->request('GET', '/referentiel', ['f' => ['statuts' => ['publiee'], 'gammes' => ['restaurant']]]);
        self::assertSelectorTextContains('table', 'Bistrot publié');
        self::assertSelectorTextNotContains('table', 'Château publié');

        // /referentiel/lieux impose la gamme.
        $client->request('GET', '/referentiel/lieux');
        self::assertSelectorTextContains('h1', 'Lieux');
        self::assertSelectorTextContains('table', 'Château publié');
        self::assertSelectorTextNotContains('table', 'Bistrot publié');
    }

    public function testActionGroupeeValideLesFichesEnAttente(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-actions@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château à valider');
        $lieu->fiche()->submitForValidation('editor');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $ficheId = $lieu->fiche()->idString();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        // Le bouton du bandeau porte l'action : « Valider » pose selection[action].
        $form = $crawler->selectButton('Valider')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$ficheId];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s)');
        $entityManager->clear();
        $fiche = $entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertSame(StatutFiche::Validee, $fiche->status());
    }

    public function testActionGroupeeEnvoieLesAccesEnDedupliquantLesEmails(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-acces@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $chateau = new Lieu();
        $chateau->changeLabel('Château A');
        $entityManager->persist($chateau);
        $manoir = new Lieu();
        $manoir->changeLabel('Manoir B');
        $entityManager->persist($manoir);

        $alice = new FicheCollaborateur('alice@example.test');
        $bob = new FicheCollaborateur('bob@example.test');
        $carol = new FicheCollaborateur('carol@example.test');
        $carol->deactivate();
        $entityManager->persist($alice);
        $entityManager->persist($bob);
        $entityManager->persist($carol);

        // Alice sur les deux fiches (email en double sur la sélection), Bob en
        // contact de repli, Carol inactive : un seul message pour Alice.
        $entityManager->persist(new FicheAffiliation($alice, $chateau->fiche(), FicheAffiliationRole::Manager, $user));
        $entityManager->persist(new FicheAffiliation($bob, $chateau->fiche(), FicheAffiliationRole::Utilisateur, $user, repli: true));
        $entityManager->persist(new FicheAffiliation($alice, $manoir->fiche(), FicheAffiliationRole::Manager, $user));
        $entityManager->persist(new FicheAffiliation($carol, $manoir->fiche(), FicheAffiliationRole::Utilisateur, $user));
        $entityManager->flush();
        $ids = [$chateau->fiche()->idString(), $manoir->fiche()->idString()];
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Envoyer les accès extranet')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = $ids;
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s), 3 ignoré(s)');
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM outbox_message WHERE message_type LIKE '%CollaborateurAccessRequested'",
        ));
        /** @var array{collaborateurId: string, ficheId: string} $body */
        $body = json_decode((string) $this->connection->fetchOne(
            "SELECT body FROM outbox_message WHERE message_type LIKE '%CollaborateurAccessRequested'",
        ), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($alice->id(), $body['collaborateurId']);
    }

    public function testLActionAccesEstPlafonneeACinqCents(): void
    {
        self::bootKernel();
        $actionneur = self::getContainer()->get(ReferentielActionGroupee::class);
        $ids = array_map(static fn (): string => (string) new Ulid(), range(1, 501));

        $this->expectException(\DomainException::class);
        $actionneur->appliquer('acces', $ids, 'actor');
    }

    public function testActionGroupeeAssigneUnContributeurSansToucherLeWorkflow(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('contributeur@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château assigné');
        $lieu->fiche()->publishForImport();
        $entityManager->persist($lieu);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot libre');
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $ficheId = $lieu->fiche()->idString();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        // Le bloc « Attribuer » du menu : un seul bouton Valider (action `attribuer`).
        $form = $crawler->filter('button[name="selection[action]"][value="attribuer"]')->form();
        $form['selection[contributeur]'] = $user->id();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$ficheId];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s)');
        $entityManager->clear();
        $fiche = $entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertSame($user->id(), $fiche->assignee()?->id());
        // L'assignation est une mise à jour technique : le workflow ne bouge pas.
        self::assertSame(StatutFiche::Publiee, $fiche->status());

        // Facette « Contributeur » : seule la fiche assignée reste dans la liste.
        $crawler = $client->request('GET', '/referentiel', ['f' => ['contributeurs' => [$user->id()]]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Château assigné');
        self::assertSelectorTextNotContains('table', 'Bistrot libre');
        self::assertSelectorTextContains('table', 'contributeur@example.test');
        self::assertStringContainsString('Mes fiches', $crawler->text(null, true));
    }

    public function testActionGroupeeAttribueLaVisibiliteSansToucherLeWorkflow(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('visibilite@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $marketplace = new SiteDiffusion('MARKETPLACE_TEST', 'Marketplace', 'Réseau', false, false, 0, []);
        $portail = new SiteDiffusion('PORTAIL_TEST', 'Portail', 'Réseau', false, false, 1, []);
        $entityManager->persist($marketplace);
        $entityManager->persist($portail);

        $sansSites = new Lieu();
        $sansSites->changeLabel('Château sans canal');
        $sansSites->fiche()->publishForImport();
        $entityManager->persist($sansSites);
        $dejaServie = new Lieu();
        $dejaServie->changeLabel('Château déjà diffusé');
        $dejaServie->fiche()->replaceSiteDiffusion([$marketplace, $portail]);
        $entityManager->persist($dejaServie);
        $entityManager->flush();
        $ficheId = $sansSites->fiche()->idString();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Attribuer la visibilité');
        $form = $crawler->filter('button[name="selection[action]"][value="attribuer"]')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$ficheId, $dejaServie->fiche()->idString()];
        $values['selection']['sites'] = [(string) $marketplace->id(), (string) $portail->id()];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        // La fiche déjà servie est comptée ignorée, rien n'est retiré.
        self::assertSelectorTextContains('body', '1 élément(s) traité(s), 1 ignoré(s)');
        $entityManager->clear();
        $fiche = $entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertEqualsCanonicalizing(
            [$marketplace->id(), $portail->id()],
            $fiche->siteDiffusionIds(),
        );
        // Attribuer un canal est une mise à jour technique : le workflow ne bouge pas.
        self::assertSame(StatutFiche::Publiee, $fiche->status());

        // Sans site coché, l'action est refusée.
        $values['selection']['sites'] = [];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Choisissez un contributeur à assigner ou des sites à attribuer.');
    }

    public function testLaRechercheTolereLesMotsSousLaTailleDIndexation(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('recherche@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Le Grand Pavillon Chantilly');
        $lieu->fiche()->publishForImport();
        $entityManager->persist($lieu);
        $entityManager->flush();
        self::getContainer()->get(FicheSearchIndexer::class)->index($lieu->fiche());
        $client->loginUser($user);

        // Le nom exact contient « Le », absent de l'index FULLTEXT (min 3 lettres).
        $client->request('GET', '/referentiel', ['f' => ['q' => 'Le Grand Pavillon Chantilly']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Le Grand Pavillon Chantilly');
    }

    public function testVueEnregistreePuisSupprimee(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-vues@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel', ['f' => ['statuts' => ['publiee']]]);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la vue')->form();
        $form['vue[name]'] = 'Publiées';
        $client->submit($form);
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vue « Publiées » enregistrée.');
        $lien = $crawler->filter('a:contains("Publiées")');
        self::assertGreaterThan(0, $lien->count());
        self::assertStringContainsString('statuts', (string) $lien->attr('href'));

        $form = $crawler->selectButton('Supprimer')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vue « Publiées » supprimée.');
    }

    public function testTriParNomEnTetesCliquablesEtPaginationKeyset(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-tri@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        // Créées dans le désordre alphabétique : le tri par défaut
        // (modification décroissante) ne coïncide pas avec le tri par nom.
        foreach (['Zzz pavillon', 'Aaa château', 'Mmm manoir'] as $label) {
            $lieu = new Lieu();
            $lieu->changeLabel($label);
            $entityManager->persist($lieu);
        }
        $entityManager->flush();
        $client->loginUser($user);

        // Tri par nom ascendant : ordre alphabétique et libellé de tri affiché.
        $crawler = $client->request('GET', '/referentiel', ['f' => ['tri' => 'nom_asc']]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Tri : nom (A → Z)', $crawler->text(null, true));
        self::assertStringContainsString('Aaa château', $crawler->filter('tbody tr')->first()->text(null, true));

        // L'en-tête actif bascule vers le sens inverse, sans transporter de curseur.
        $lien = (string) $crawler->filter('thead a[aria-label="Trier par nom et ville"]')->attr('href');
        self::assertStringContainsString('nom_desc', $lien);
        self::assertStringNotContainsString('cursor', $lien);
        // Un en-tête inactif part sur son sens naturel.
        self::assertStringContainsString(
            'completude_desc',
            (string) $crawler->filter('thead a[aria-label="Trier par complétude"]')->attr('href'),
        );

        $crawler = $client->request('GET', '/referentiel', ['f' => ['tri' => 'nom_desc']]);
        self::assertStringContainsString('Zzz pavillon', $crawler->filter('tbody tr')->first()->text(null, true));

        // Pagination keyset sous tri non défaut : pages de 1, ni doublon ni trou.
        $provider = self::getContainer()->get(ReferentielListeProvider::class);
        $filtres = ReferentielFiltres::fromArray(['tri' => 'nom_asc']);
        $labels = [];
        $cursor = null;
        do {
            $vue = $provider->vue($filtres, $cursor, 1);
            foreach ($vue->lignes as $ligne) {
                $labels[] = $ligne->label;
            }
            $cursor = ReferentielCursor::decode($vue->nextCursor);
        } while (null !== $cursor);
        self::assertSame(['Aaa château', 'Mmm manoir', 'Zzz pavillon'], $labels);

        // Un curseur forgé sous un autre tri est rejeté comme invalide.
        $client->request('GET', '/referentiel', [
            'f' => ['tri' => 'statut_asc'],
            'cursor' => (new ReferentielCursor(TriReferentiel::NomAsc, 'Aaa château', new Ulid()))->encode(),
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testLaVueEnregistreeConserveLeTri(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-vue-tri@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel', ['f' => ['tri' => 'nom_asc']]);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la vue')->form();
        $form['vue[name]'] = 'Par nom';
        $client->submit($form);
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vue « Par nom » enregistrée.');
        self::assertStringContainsString(
            'nom_asc',
            (string) $this->connection->fetchOne('SELECT filters FROM pim_saved_view'),
        );
        // La vue ré-appliquée restitue le tri via son URL.
        $lien = $crawler->filter('a:contains("Par nom")');
        self::assertGreaterThan(0, $lien->count());
        self::assertStringContainsString('nom_asc', (string) $lien->attr('href'));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_saved_view');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
