<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\CritereGeo;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Visibilité géographique automatique (CDC §10.1) : critères dans l'admin des
 * sites, attribution à la création, bouton « Appliquer les sites
 * automatiques », commande de rattrapage.
 */
#[Group('database')]
final class VisibiliteGeoTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->entityManager->clear();
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testLAdminSaisitDesCriteresGeographiques(): void
    {
        $client = $this->createClientWithUser(['ROLE_ADMIN']);

        // L'endpoint de suggestions répond même sans géocodeur configuré.
        $client->request('GET', '/admin/sites-de-diffusion/ville-autocomplete?q=tours&pays=fr');
        self::assertResponseIsSuccessful();
        self::assertSame(['suggestions' => []], json_decode((string) $client->getResponse()->getContent(), true));

        // Création d'un site avec deux zones : Tours + 10 km et la Bretagne.
        $crawler = $client->request('GET', '/admin/sites-de-diffusion/ajouter');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['site_diffusion']['code'] = 'GEO_TEST';
        $values['site_diffusion']['label'] = 'Site géo';
        $values['site_diffusion']['groupe'] = 'Sites régionaux';
        $values['site_diffusion']['position'] = '1';
        $values['site_diffusion']['actif'] = '1';
        $values['site_diffusion']['criteresGeo'] = [
            ['type' => 'ville', 'villePays' => 'FR', 'ville' => 'Tours, Indre-et-Loire, France', 'latitude' => '47.394144', 'longitude' => '0.68484', 'rayonKm' => '10'],
            ['type' => 'region', 'region' => 'Bretagne'],
        ];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/admin/sites-de-diffusion');

        $sites = self::getContainer()->get(SiteDiffusionRepository::class);
        $site = $sites->findOneByCode('GEO_TEST');
        self::assertInstanceOf(SiteDiffusion::class, $site);
        $criteres = $site->criteresGeo();
        self::assertCount(2, $criteres);
        self::assertSame('Tours, Indre-et-Loire, France + 10 km', $criteres[0]->resume());
        self::assertSame('Région Bretagne', $criteres[1]->resume());

        // La liste résume les zones.
        $client->request('GET', '/admin/sites-de-diffusion');
        self::assertSelectorTextContains('table', 'Région Bretagne');

        // Une ville saisie librement, sans coordonnées géocodées, est refusée.
        $values['site_diffusion']['code'] = 'GEO_REFUSE_TEST';
        $values['site_diffusion']['criteresGeo'] = [
            ['type' => 'ville', 'villePays' => 'FR', 'ville' => 'Tours', 'latitude' => '', 'longitude' => '', 'rayonKm' => '10'],
        ];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertSelectorTextContains('body', 'Choisissez une ville dans la liste de suggestions.');
        self::assertNull($sites->findOneByCode('GEO_REFUSE_TEST'));
    }

    public function testLaCreationAttribueLesSitesDontLaZoneCouvreLAdresse(): void
    {
        $client = $this->createClientWithUser(['ROLE_BP_VALIDATOR']);
        $this->seedSiteTours();
        // Site hors zone : jamais attribué.
        $this->entityManager->persist(new SiteDiffusion('GEO_BRETAGNE_TEST', 'Bretagne test', 'Sites régionaux', position: 2, criteresGeo: [CritereGeo::region('Bretagne')]));
        $this->entityManager->flush();

        // Adresse dans le rayon de Tours (coordonnées appliquées par la
        // recherche d'adresse du tunnel).
        $client->request('GET', '/referentiel/fiche/nouvelle?type=lieu');
        self::assertResponseIsSuccessful();
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Hôtel du test géo',
            'fiche_creation[localisation][ville]' => 'Tours',
            'fiche_creation[localisation][codePostal]' => '37000',
            'fiche_creation[localisation][countryCode]' => 'FR',
            'fiche_creation[localisation][latitude]' => '47.39',
            'fiche_creation[localisation][longitude]' => '0.69',
        ]);
        self::assertResponseRedirects();

        $codes = $this->connection->fetchFirstColumn(
            'SELECT s.code FROM pim_fiche_site_diffusion l JOIN pim_site_diffusion s ON s.id = l.site_id ORDER BY s.code',
        );
        self::assertSame(['GEO_TOURS_TEST'], $codes);
    }

    public function testLeBoutonAppliqueLesSitesSansDepublierLaFiche(): void
    {
        $client = $this->createClientWithUser(['ROLE_BP_VALIDATOR']);
        $this->seedSiteTours();

        $lieu = new Lieu();
        $lieu->changeLabel('Château de Tours');
        $localisation = new Localisation();
        $localisation->changeVille('Tours');
        $localisation->changeCountryCode('FR');
        $localisation->changeLatitude('47.39');
        $localisation->changeLongitude('0.69');
        $lieu->fiche()->changeLocalisation($localisation);
        $lieu->fiche()->submitForValidation('test');
        $lieu->fiche()->validate('test');
        $lieu->fiche()->publish();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $id = (string) $lieu->id();

        // Section Visibilité : le bouton est là, à côté de la sélection des sites.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=14');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Appliquer les sites automatiques')->form();
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '1 site(s) ajouté(s) selon les critères géographiques.');

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_site_diffusion'));
        // Mise à jour technique : la fiche publiée le reste.
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));

        // Second clic : plus rien à ajouter.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=14');
        $form = $crawler->selectButton('Appliquer les sites automatiques')->form();
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Aucun site à ajouter');
    }

    public function testLaCommandeDeRattrapageRespecteLeDryRun(): void
    {
        $this->createClientWithUser(['ROLE_ADMIN']);
        $this->seedSiteTours();

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir du rattrapage');
        $localisation = new Localisation();
        $localisation->changeVille('Tours');
        $localisation->changeCountryCode('FR');
        $localisation->changeLatitude('47.40');
        $localisation->changeLongitude('0.70');
        $lieu->fiche()->changeLocalisation($localisation);
        $this->entityManager->persist($lieu);
        // Fiche sans coordonnées : ignorée par le critère ville.
        $sansGps = new Lieu();
        $sansGps->changeLabel('Auberge sans GPS');
        $this->entityManager->persist($sansGps);
        $this->entityManager->flush();

        $tester = new CommandTester((new Application(self::$kernel))->find('app:pim:attribuer-visibilite-geo'));

        $tester->execute(['--dry-run' => true]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Manoir du rattrapage (Tours)', $tester->getDisplay());
        self::assertStringContainsString('1 fiche(s) concernée(s), 1 attribution(s)', $tester->getDisplay());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_site_diffusion'));

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_site_diffusion'));
        // Rejouée, la commande ne trouve plus rien à ajouter (idempotence).
        $tester->execute([]);
        self::assertStringContainsString('0 fiche(s) concernée(s), 0 attribution(s)', $tester->getDisplay());
    }

    /** @param list<string> $roles */
    private function createClientWithUser(array $roles): KernelBrowser
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clearTables();

        $user = new User('visibilite-geo@example.test', $roles);
        $user->setPassword('not-used-by-login-user');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function seedSiteTours(): void
    {
        $this->entityManager->persist(new SiteDiffusion(
            'GEO_TOURS_TEST',
            'Tours test',
            'Sites régionaux',
            position: 1,
            criteresGeo: [CritereGeo::ville('Tours, Indre-et-Loire, France', '47.394144', '0.68484', 10)],
        ));
        $this->entityManager->flush();
    }

    private function clearTables(): void
    {
        foreach ([
            'outbox_message',
            'pim_fiche_search',
            'pim_fiche_site_diffusion',
            'pim_fiche_attribute_value',
            'pim_fiche_affiliation',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_restaurant',
            'pim_activite',
            'pim_service_evenementiel',
            'pim_fiche',
            'pim_localisation',
            'pim_fiche_collaborateur',
            'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
    }
}
