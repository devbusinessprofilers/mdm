<?php

declare(strict_types=1);

namespace App\Tests\Pim\Fusion;

use App\Account\Entity\User;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\StatutFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class ReferentielFusionControllerTest extends WebTestCase
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

    public function testParcoursCompletDeLaFusionDepuisLeBandeau(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('fusion-parcours@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used');
        $entityManager->persist($user);
        $lieuA = new Lieu();
        $lieuA->changeLabel('Manoir original');
        $lieuA->changeGeneraleWebsiteUrl('https://ancien.example.test');
        $entityManager->persist($lieuA);
        $lieuB = new Lieu();
        $lieuB->changeLabel('Manoir doublon');
        $lieuB->changeGeneraleWebsiteUrl('https://recent.example.test');
        $entityManager->persist($lieuB);
        $entityManager->flush();
        $idA = $lieuA->fiche()->idString();
        $idB = $lieuB->fiche()->idString();
        $client->loginUser($user);

        // 1. Le bouton du bandeau poste la sélection vers la route de fusion.
        $crawler = $client->request('GET', '/referentiel');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Fusionner les doublons')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$idA, $idB];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $ecranUrl = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/referentiel/fusion/', $ecranUrl);

        // 2. L'écran de comparaison montre les valeurs divergentes des deux fiches.
        $crawler = $client->request('GET', $ecranUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Fusionner deux fiches');
        self::assertSelectorTextContains('body', 'Manoir original');
        self::assertSelectorTextContains('body', 'Manoir doublon');
        self::assertSelectorTextContains('body', 'https://ancien.example.test');
        self::assertSelectorTextContains('body', 'https://recent.example.test');

        // 3. Soumission avec la fiche A survivante et le site internet de B.
        $form = $crawler->selectButton('Fusionner les fiches')->form();
        $values = $form->getPhpValues();
        $values['fusion']['survivant'] = 'a';
        $values['fusion']['champ_generale_website_url'] = 'b';
        $values['fusion']['champ_label'] = 'a';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fusion appliquée');

        $entityManager->clear();
        $survivante = $entityManager->find(Fiche::class, $idA);
        $absorbee = $entityManager->find(Fiche::class, $idB);
        self::assertInstanceOf(Fiche::class, $survivante);
        self::assertInstanceOf(Fiche::class, $absorbee);
        self::assertSame('Manoir original', $survivante->label());
        self::assertSame(StatutFiche::Archivee, $absorbee->status());
        self::assertNotNull($absorbee->mergedIntoId());
        self::assertTrue($survivante->id()->equals($absorbee->mergedIntoId()));
        // La révision d'audit de la fusion porte l'action « merge ».
        $actions = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT action FROM audit_revision WHERE fiche_id IN (?, ?)',
            [$survivante->id()->toBinary(), $absorbee->id()->toBinary()],
        );
        self::assertContains('merge', $actions);
    }

    public function testRefuseUneSelectionQuiNEstPasDeuxFichesDUneMemeGamme(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('fusion-refus@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu seul');
        $entityManager->persist($lieu);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Restaurant seul');
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel');
        $form = $crawler->selectButton('Fusionner les doublons')->form();

        // Une seule fiche cochée.
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$lieu->fiche()->idString()];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'exactement deux fiches');

        // Deux gammes différentes.
        $crawler = $client->request('GET', '/referentiel');
        $form = $crawler->selectButton('Fusionner les doublons')->form();
        $values = $form->getPhpValues();
        $values['selection']['ids'] = [$lieu->fiche()->idString(), $restaurant->fiche()->idString()];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'même gamme');
    }

    public function testVerrouOptimisteQuandUneFicheChangeEntreLEcranEtLaSoumission(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('fusion-verrou@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used');
        $entityManager->persist($user);
        $lieuA = new Lieu();
        $lieuA->changeLabel('Version A');
        $entityManager->persist($lieuA);
        $lieuB = new Lieu();
        $lieuB->changeLabel('Version B');
        $entityManager->persist($lieuB);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', sprintf('/referentiel/fusion/%s/%s', $lieuA->fiche()->idString(), $lieuB->fiche()->idString()));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Fusionner les fiches')->form();
        $values = $form->getPhpValues();

        // La fiche B change entre l'affichage et la soumission.
        $this->connection->executeStatement(
            'UPDATE pim_fiche SET version = version + 1 WHERE id = ?',
            [$lieuB->fiche()->id()->toBinary()],
        );

        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'modifiées depuis l’ouverture');
        $entityManager->clear();
        $ficheB = $entityManager->find(Fiche::class, $lieuB->fiche()->idString());
        self::assertInstanceOf(Fiche::class, $ficheB);
        self::assertNotSame(StatutFiche::Archivee, $ficheB->status());
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement("DELETE FROM account_user WHERE email LIKE 'fusion-%'");
    }
}
