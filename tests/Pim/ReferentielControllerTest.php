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
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TriReferentiel;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\ReadModel\ReferentielCursor;
use App\Pim\Service\FicheSearchIndexer;
use App\Pim\Service\ReferentielActionGroupee;
use App\Pim\Service\ReferentielListeProvider;
use App\Shared\Service\PrivateObjectStorageInterface;
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

    public function testActionGroupeeSoumetLesFichesEnCours(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-soumettre@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        // Une fiche neuve est en cours : c'est le pas manquant du workflow que
        // l'action de masse « Soumettre à validation » vient combler.
        $lieu = new Lieu();
        $lieu->changeLabel('Château à soumettre');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $ficheId = $lieu->fiche()->idString();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Soumettre à validation')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$ficheId];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s)');
        $entityManager->clear();
        $fiche = $entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertSame(StatutFiche::EnAttenteValidation, $fiche->status());
    }

    public function testActionGroupeeArchiveDepuisNimporteQuelStatut(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-archiver@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        // Archiver doit fonctionner quel que soit le statut, pas seulement
        // depuis « publiée » : ici une fiche encore en cours.
        $lieu = new Lieu();
        $lieu->changeLabel('Château à archiver');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $ficheId = $lieu->fiche()->idString();
        self::assertSame(StatutFiche::EnCours, $lieu->fiche()->status());
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Archiver')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$ficheId];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s)');
        $entityManager->clear();
        $fiche = $entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertSame(StatutFiche::Archivee, $fiche->status());
    }

    public function testActionGroupeePublierRespecteLesObligationsPhotos(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-publier@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        // Deux restaurants validés (min photos « autres » = 1 + principale) :
        // l'un conforme est publié, l'autre sans photo est ignoré.
        // La photo est ajoutée avant le passage à « validée » : addResource()
        // appelle markChanged(), qui rétrograderait sinon la fiche en cours.
        $conforme = new Restaurant();
        $conforme->changeLabel('Bistrot conforme');
        $photo = new RessourceLieu();
        $photo->changeDamAssetId((string) new Ulid());
        $photo->changeNature(NatureRessource::Photo);
        $photo->changeUsage('PHOTO_PRINCIPALE');
        $photo->changePosition(1);
        $conforme->fiche()->addResource($photo);
        $this->porterAValidee($conforme->fiche());
        $entityManager->persist($conforme);

        $sansPhoto = new Restaurant();
        $sansPhoto->changeLabel('Bistrot sans photo');
        $this->porterAValidee($sansPhoto->fiche());
        $entityManager->persist($sansPhoto);

        $entityManager->flush();
        $idConforme = $conforme->fiche()->idString();
        $idSansPhoto = $sansPhoto->fiche()->idString();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Publier')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$idConforme, $idSansPhoto];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s), 1 ignoré(s)');
        $entityManager->clear();
        self::assertSame(StatutFiche::Publiee, $entityManager->find(Fiche::class, $idConforme)?->status());
        self::assertSame(StatutFiche::Validee, $entityManager->find(Fiche::class, $idSansPhoto)?->status());
    }

    public function testActionGroupeeDesarchiveVersEnCours(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-desarchiver@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château archivé');
        $lieu->fiche()->archive('setup');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $ficheId = $lieu->fiche()->idString();
        self::assertSame(StatutFiche::Archivee, $lieu->fiche()->status());
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Désarchiver')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$ficheId];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s)');
        $entityManager->clear();
        $fiche = $entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertSame(StatutFiche::EnCours, $fiche->status());
        self::assertNull($fiche->archivedAt());
    }

    public function testActionGroupeeRepublieUneFicheArchiveeConforme(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-republier@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        // Une fiche archivée conforme (1 photo principale) est republiée,
        // une fiche archivée sans photo est ignorée (garde photos).
        $conforme = new Restaurant();
        $conforme->changeLabel('Bistrot à republier');
        $photo = new RessourceLieu();
        $photo->changeDamAssetId((string) new Ulid());
        $photo->changeNature(NatureRessource::Photo);
        $photo->changeUsage('PHOTO_PRINCIPALE');
        $photo->changePosition(1);
        $conforme->fiche()->addResource($photo);
        $conforme->fiche()->archive('setup');
        $entityManager->persist($conforme);

        $sansPhoto = new Restaurant();
        $sansPhoto->changeLabel('Bistrot archivé sans photo');
        $sansPhoto->fiche()->archive('setup');
        $entityManager->persist($sansPhoto);

        $entityManager->flush();
        $idConforme = $conforme->fiche()->idString();
        $idSansPhoto = $sansPhoto->fiche()->idString();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Republier')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$idConforme, $idSansPhoto];
        $client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 élément(s) traité(s), 1 ignoré(s)');
        $entityManager->clear();
        self::assertSame(StatutFiche::Publiee, $entityManager->find(Fiche::class, $idConforme)?->status());
        self::assertSame(StatutFiche::Archivee, $entityManager->find(Fiche::class, $idSansPhoto)?->status());
    }

    /** Amène une fiche neuve jusqu'au statut « validée » par le workflow normal. */
    private function porterAValidee(Fiche $fiche): void
    {
        $fiche->submitForValidation('editor');
        $fiche->validate('validator');
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

    public function testLaRechercheCorrigeLesFautesQuandLaSaisieStricteNeDonneRien(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('recherche-fautes@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        // « Hôtel La Pomme » rend « pomme » légitime dans le vocabulaire : la
        // correction doit passer par la substitution de mot connu.
        foreach (['Auberge du Jeu de Paume', 'Hôtel La Pomme'] as $label) {
            $lieu = new Lieu();
            $lieu->changeLabel($label);
            $lieu->fiche()->publishForImport();
            $entityManager->persist($lieu);
            $entityManager->flush();
            self::getContainer()->get(FicheSearchIndexer::class)->index($lieu->fiche());
        }
        $client->loginUser($user);

        // « pomme » combiné à « auberge » et « jeu » ne matche rien : la
        // requête est corrigée vers « paume », la fiche sort, le bandeau
        // explique — et le champ garde la saisie.
        $crawler = $client->request('GET', '/referentiel', ['f' => ['q' => 'Auberge du jeu de la pomme']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Auberge du Jeu de Paume');
        self::assertStringContainsString('Résultats pour', $crawler->text(null, true));
        self::assertStringContainsString('paume', $crawler->text(null, true));
        self::assertSame('Auberge du jeu de la pomme', $crawler->filter('#recherche-referentiel')->attr('value'));

        // Saisie correcte : pas de bandeau.
        $crawler = $client->request('GET', '/referentiel', ['f' => ['q' => 'Auberge du Jeu de Paume']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Auberge du Jeu de Paume');
        self::assertStringNotContainsString('Résultats pour', $crawler->text(null, true));

        // Saisie incorrigeable : zéro résultat, pas de bandeau.
        $crawler = $client->request('GET', '/referentiel', ['f' => ['q' => 'zzzzzzzz']]);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Résultats pour', $crawler->text(null, true));
        self::assertStringNotContainsString('Auberge du Jeu de Paume', $crawler->text(null, true));
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

    public function testLExportExcelEstTraceGenereEnFondEtTelechargeable(): void
    {
        $client = self::createClient();
        // Le conteneur survit aux requêtes : le stockage factice posé ici
        // sert au handler puis au téléchargement.
        $client->disableReboot();
        $stockage = new ReferentielExportTestStorage();
        self::getContainer()->set(PrivateObjectStorageInterface::class, $stockage);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-export@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château à exporter');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_1']);
        $entityManager->persist($lieu);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot à exporter');
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        // La modale d'export est présente, ses cases précochées, son bouton
        // vise la demande d'export dans un nouvel onglet.
        self::assertGreaterThan(0, $crawler->filter('[data-modal="export-colonnes"]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-modal="export-colonnes"] input[checked]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-modal="export-colonnes"] [formtarget="_blank"]')->count());

        $form = $crawler->selectButton('Valider')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$lieu->fiche()->idString(), $restaurant->fiche()->idString()];
        $values['selection']['colonnes'] = ['lieu:label', 'lieu:generale_typologie', 'restaurant:label'];

        // 1. La demande crée l'export (code unique) et mène à la page de suivi.
        $client->request('POST', '/referentiel/exporter', $values);
        self::assertResponseRedirects();
        $suivi = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/referentiel/exports/[0-9A-HJKMNP-TV-Z]{26}$#', $suivi);
        $exportId = substr($suivi, -26);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Votre fichier est en cours de génération');
        self::assertSelectorTextContains('body', 'télécharger automatiquement votre fichier');
        self::assertSelectorTextContains('body', 'Référence de l\'export : '.$exportId);

        // 2. Le statut se sonde en JSON ; le worker génère puis termine.
        $client->request('GET', $suivi.'/statut');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"statut":"en_attente"', (string) $client->getResponse()->getContent());

        $handler = self::getContainer()->get(\App\Pim\MessageHandler\GenererReferentielExportHandler::class);
        $handler(new \App\Pim\Message\GenererReferentielExport($exportId));

        $client->request('GET', $suivi.'/statut');
        self::assertStringContainsString('"statut":"terminee"', (string) $client->getResponse()->getContent());

        // 3. La page rouverte plus tard propose directement le téléchargement.
        $client->request('GET', $suivi);
        self::assertSelectorTextContains('body', 'Votre fichier est prêt');

        // 4. Le classeur téléchargé porte les colonnes choisies.
        $client->request('GET', $suivi.'/fichier');
        self::assertResponseIsSuccessful();
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $client->getResponse()->headers->get('Content-Type'),
        );
        self::assertStringContainsString('referentiel-export-', (string) $client->getResponse()->headers->get('Content-Disposition'));

        $chemin = tempnam(sys_get_temp_dir(), 'mdm-export-lu');
        self::assertIsString($chemin);
        file_put_contents($chemin, $client->getInternalResponse()->getContent());
        try {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($chemin);
            $feuilles = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(static fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : '', $row->toArray());
                }
                $feuilles[$sheet->getName()] = $rows;
            }
            $reader->close();

            self::assertSame(['Lieux', 'Restaurants', 'LOV'], array_keys($feuilles));
            // Colonnes retenues seulement (atout1, non cochée, est absente),
            // le code de fiche en tête, les colonnes LOV en libellés.
            self::assertSame(['code', 'label', 'generale_typologie'], $feuilles['Lieux'][0]);
            self::assertSame('Château à exporter', $feuilles['Lieux'][1][1]);
            self::assertSame('Hôtel 2 étoiles', $feuilles['Lieux'][1][2]);
            self::assertSame('Bistrot à exporter', $feuilles['Restaurants'][1][1]);
        } finally {
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }

        // 5. L'export figure dans Outils → Historique des exports, avec sa
        // date d'expiration (rétention 30 jours sur le bucket privé).
        $crawler = $client->request('GET', '/outils', ['famille' => 'export']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Export Excel · 2 fiche(s) · referentiel-export@example.test');
        self::assertStringContainsString('Expiration', $crawler->text(null, true));
        self::assertStringContainsString((new \DateTimeImmutable('+30 days'))->format('d/m/Y'), $crawler->text(null, true));
    }

    public function testLExportDeToutLeResultatFiltreNeStockePasLesIds(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(PrivateObjectStorageInterface::class, new ReferentielExportTestStorage());
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-export-tout@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château du filtre');
        $entityManager->persist($lieu);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot hors filtre');
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel', ['f' => ['gammes' => ['lieu']]]);
        $form = $crawler->selectButton('Valider')->form();
        $values = $form->getPhpValues();
        $values['selection']['tout'] = '1';
        $values['selection']['ids'] = [];
        $values['selection']['colonnes'] = ['lieu:label'];

        // « Tout le résultat filtré » : l'entité garde le drapeau (ids null)
        // et les filtres — pas des milliers d'ids (max_input_vars).
        $client->request('POST', '/referentiel/exporter?'.http_build_query(['f' => ['gammes' => ['lieu']]]), $values);
        self::assertResponseRedirects();
        $suivi = (string) $client->getResponse()->headers->get('Location');
        $exportId = substr($suivi, -26);
        self::assertNull($this->connection->fetchOne('SELECT ids FROM pim_referentiel_export LIMIT 1'));

        $crawler = $client->followRedirect();
        self::assertStringContainsString('1 fiche', $crawler->text(null, true));

        // Le worker re-résout la sélection depuis les filtres stockés.
        $handler = self::getContainer()->get(\App\Pim\MessageHandler\GenererReferentielExportHandler::class);
        $handler(new \App\Pim\Message\GenererReferentielExport($exportId));

        $client->request('GET', $suivi.'/fichier');
        self::assertResponseIsSuccessful();

        $chemin = tempnam(sys_get_temp_dir(), 'mdm-export-tout');
        self::assertIsString($chemin);
        file_put_contents($chemin, $client->getInternalResponse()->getContent());
        try {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($chemin);
            $noms = [];
            $contenu = '';
            foreach ($reader->getSheetIterator() as $sheet) {
                $noms[] = $sheet->getName();
                foreach ($sheet->getRowIterator() as $row) {
                    $contenu .= implode(';', array_map(static fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : '', $row->toArray()));
                }
            }
            $reader->close();
            self::assertContains('Lieux', $noms);
            self::assertNotContains('Restaurants', $noms);
            self::assertStringContainsString('Château du filtre', $contenu);
            self::assertStringNotContainsString('Bistrot hors filtre', $contenu);
        } finally {
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }
    }

    public function testLExportSansColonneNeCreeRienEtAvertit(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-export-vide@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château sans colonnes');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        $form = $crawler->selectButton('Valider')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$lieu->fiche()->idString()];
        $values['selection']['colonnes'] = [];
        $client->request('POST', '/referentiel/exporter', $values);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Choisissez au moins une colonne à exporter.');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_referentiel_export'));
    }

    public function testLaPurgeExpireLesExportsEtRetireLeClasseurDuBucket(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $stockage = new ReferentielExportTestStorage();
        self::getContainer()->set(PrivateObjectStorageInterface::class, $stockage);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('referentiel-export-purge@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château à purger');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        $form = $crawler->selectButton('Valider')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$lieu->fiche()->idString()];
        $values['selection']['colonnes'] = ['lieu:label'];
        $client->request('POST', '/referentiel/exporter', $values);
        $suivi = (string) $client->getResponse()->headers->get('Location');
        $exportId = substr($suivi, -26);

        $handler = self::getContainer()->get(\App\Pim\MessageHandler\GenererReferentielExportHandler::class);
        $handler(new \App\Pim\Message\GenererReferentielExport($exportId));
        self::assertCount(1, $stockage->objets);

        // Rétention écoulée : la purge quotidienne retire le classeur du
        // bucket et marque l'export expiré.
        $this->connection->executeStatement("UPDATE pim_referentiel_export SET expires_at = '2020-01-01 00:00:00'");
        $entityManager->clear();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:referentiel:purger-exports'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 export(s) purgé(s).', $tester->getDisplay());
        self::assertCount(0, $stockage->objets);

        $client->request('GET', $suivi.'/fichier');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', $suivi);
        self::assertSelectorTextContains('body', 'Cet export a expiré.');
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_saved_view');
        $this->connection->executeStatement('DELETE FROM pim_referentiel_export');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
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

/** Bucket privé factice des tests d'export : objets en mémoire. */
final class ReferentielExportTestStorage implements PrivateObjectStorageInterface
{
    /** @var array<string, string> */
    public array $objets = [];

    public function write(string $key, string $contents, array $options = []): void
    {
        $this->objets[$key] = $contents;
    }

    public function writeStream(string $key, mixed $stream, array $options = []): void
    {
        $this->objets[$key] = (string) stream_get_contents($stream);
    }

    public function read(string $key): string
    {
        return $this->objets[$key] ?? throw new \RuntimeException(sprintf('Objet %s absent.', $key));
    }

    public function readStream(string $key): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        if (false === $stream) {
            throw new \RuntimeException('Flux temporaire indisponible.');
        }
        fwrite($stream, $this->read($key));
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return isset($this->objets[$key]);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
    {
        return 'https://private.example.test/'.$key;
    }

    public function delete(string $key): void
    {
        unset($this->objets[$key]);
    }

    public function deleteDirectory(string $prefix): void
    {
    }
}
