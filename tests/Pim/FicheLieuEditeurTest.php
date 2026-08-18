<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Etl\Entity\FicheSalesforce;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\SiteDiffusion;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheLieuEditeurTest extends WebTestCase
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

    public function testLaSectionEnregistreSesChampsSansToucherLesAutres(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('editeur-fiche@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château des sections');
        $lieu->changeGeneraleWebsiteUrl('https://exemple.test');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $id = (string) $lieu->id();
        $client->loginUser($user);

        // Le rail des 17 sections est là (dont « Booster ma visibilité », maquette pure), la section 0 est active. L'éditeur
        // est la vue unique : suppression et médias y vivent aussi.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Château des sections');
        self::assertSame(17, $crawler->filter('nav[aria-label="Sections de la fiche"] li')->count());
        // Un éditeur ne voit pas la suppression (réservée aux validateurs).
        self::assertSelectorNotExists('.danger-form');

        // Section Médias : le gestionnaire de photos/documents du DAM est intégré.
        $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=11');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="lieu-media"]');

        // Section Salles : les collections gardent l'ajout/retrait Stimulus.
        $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=5');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="form-collection"]');

        // Soumission partielle de la section 0 : seul le label voyage,
        // le site web (hors requête) doit rester intact.
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $values['lieu']['label'] = 'Château renommé par section';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche enregistrée.');

        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame('Château renommé par section', $recharge->label());
        self::assertSame('https://exemple.test', $recharge->generaleWebsiteUrl());

        // Section Visibilité : la sélection des sites s'enregistre.
        $site = new SiteDiffusion('KACTUS_TEST', 'Kactus', 'Partenaires', false, false, 1, []);
        $entityManager->persist($site);
        $entityManager->flush();
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=14');
        self::assertResponseIsSuccessful();

        // Bloc Données Salesforce : message d'absence tant que la fiche est
        // inconnue de Salesforce, valeurs en lecture seule dès qu'une ligne
        // de refresh existe.
        self::assertSelectorTextContains('body', 'Fiche inconnue de Salesforce');
        $donnees = new FicheSalesforce($recharge->fiche()->id(), $recharge->fiche()->code());
        $donnees->mettreAJour(3.5, null, null, null, null, 4.2, 4.0, 'Redressement judiciaire', ['Compte A']);
        $entityManager->persist($donnees);
        $entityManager->flush();
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=14');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Données Salesforce');
        self::assertSelectorTextContains('body', 'Note RSE globale');
        self::assertSelectorTextContains('body', '4.2');
        self::assertSelectorTextContains('body', 'Redressement judiciaire');
        self::assertSelectorTextContains('body', 'Compte A');

        $form = $crawler->selectButton('Enregistrer la diffusion')->form();
        $values = $form->getPhpValues();
        $values['sites_diffusion']['sites'] = [(string) $site->id()];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();

        $entityManager->clear();
        $final = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $final);
        self::assertSame([$site->id()], $final->fiche()->siteDiffusionIds());
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM etl_fiche_salesforce');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
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
