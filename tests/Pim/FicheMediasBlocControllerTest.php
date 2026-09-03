<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le bloc médias de l'éditeur est re-rendu seul après chaque action média : le
 * wrapper medias-bloc remplace son contenu par le rendu de app_pim_*_medias_bloc
 * au lieu de recharger la page — la saisie du formulaire principal survit.
 */
#[Group('database')]
final class FicheMediasBlocControllerTest extends WebTestCase
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

    public function testLeBlocEstServiSeulEtBrancheDansLaPage(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('bloc-medias@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir du bloc');
        $photo = new RessourceLieu();
        $photo->changeDamAssetId('asset-inexistant');
        $photo->changeNature(NatureRessource::Photo);
        $photo->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($photo);
        $plan = new RessourceLieu();
        $plan->configureDocument(DocumentUsage::GeneralPlan);
        $plan->changeDamAssetId((string) new \Symfony\Component\Uid\Ulid());
        $plan->changeLegende('Plan général');
        $lieu->addRessource($plan);
        $entityManager->persist($lieu);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot du bloc');
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $client->loginUser($user);

        // La page fiche branche le shell des onglets internes et le wrapper
        // medias-bloc sur l'URL du bloc.
        $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id().'?section=11');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="media-tabs"]');
        self::assertSelectorExists('button[data-media-tabs-onglet-param="photos"]');
        self::assertSelectorExists('button[data-media-tabs-onglet-param="video"]:not([disabled])');
        self::assertSelectorExists('button[data-media-tabs-onglet-param="plans"]:not([disabled])');
        // Le lien vidéo du Lieu vit dans l'onglet Vidéo, rattaché au
        // formulaire principal par l'attribut HTML natif form.
        self::assertSelectorExists('[data-onglet="video"] [form="form-fiche"]');
        self::assertSelectorExists('[data-controller="medias-bloc"]');
        self::assertSelectorExists('[data-medias-bloc-url-value="/referentiel/lieux/fiche/'.$lieu->id().'/medias/bloc"]');

        // L'endpoint lieu rend le bloc complet : galerie pilotée par
        // lieu-media, panneaux par onglet et documents répartis (le plan
        // général dans Plans, absent des Documents administratifs).
        $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id().'/medias/bloc');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="lieu-media"]');
        self::assertSelectorExists('[data-modal-trigger-button-modal-identifier-value="photo-'.$photo->id().'"]');
        self::assertSelectorExists('[data-onglet="plans"] [data-modal-trigger-button-modal-identifier-value="document-'.$plan->id().'"]');
        $documentsAdmin = $client->getCrawler()->filter('[data-onglet="documents"] [data-modal-trigger-button-modal-identifier-value="document-'.$plan->id().'"]');
        self::assertCount(0, $documentsAdmin);

        // L'endpoint gamme rend les deux cartes (photos + documents).
        $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id().'/medias/bloc');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section', 'Photos de la fiche');
        self::assertSelectorExists('[data-controller="lieu-media"]');
    }

    public function testOngletsSansObjetSontGrisesPourUnService(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('onglets-service@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Service des onglets');
        $entityManager->persist($service);
        $entityManager->flush();
        $client->loginUser($user);

        // Section Médias d'un Service : Plans et Documents grisés (aucun
        // usage documentaire possible), Supports et Vidéo actifs.
        $client->request('GET', '/referentiel/services/fiche/'.$service->id().'?section=6');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[data-media-tabs-onglet-param="plans"][disabled]');
        self::assertSelectorExists('button[data-media-tabs-onglet-param="documents"][disabled]');
        self::assertSelectorExists('button[data-media-tabs-onglet-param="supports"]:not([disabled])');
        self::assertSelectorExists('[data-onglet="video"] [form="form-fiche"]');
    }

    public function testLeSelectInlineChangeLaCategorieSansToucherLesMetadonnees(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('categorie-inline@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Manoir des catégories');
        $photo = new RessourceLieu();
        $photo->changeDamAssetId('asset-categorie');
        $photo->changeNature(NatureRessource::Photo);
        $photo->changeUsage('PHOTO_DIVERSE');
        $photo->changeLegende('Vue du parc');
        $lieu->addRessource($photo);
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        // Le jeton CSRF du bloc médias protège aussi l'endpoint catégorie.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id().'/medias/bloc');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('[data-lieu-media-token-value]')->attr('data-lieu-media-token-value');

        $client->request('PATCH', '/referentiel/lieux/fiche/'.$lieu->id().'/photos/'.$photo->id().'/categorie', [], [], [
            'HTTP_X_CSRF_TOKEN' => $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['usage' => 'PHOTO_FACADE'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('PHOTO_FACADE', $this->connection->fetchOne('SELECT usage_code FROM pim_ressource_lieu'));
        self::assertSame('Vue du parc', $this->connection->fetchOne('SELECT legende FROM pim_ressource_lieu'));

        // Catégorie inconnue : 422, rien n'est écrit.
        $client->request('PATCH', '/referentiel/lieux/fiche/'.$lieu->id().'/photos/'.$photo->id().'/categorie', [], [], [
            'HTTP_X_CSRF_TOKEN' => $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['usage' => 'CATEGORIE_INCONNUE'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('PHOTO_FACADE', $this->connection->fetchOne('SELECT usage_code FROM pim_ressource_lieu'));
    }

    public function testActionsDocumentsRepondentEnJsonQuandAjax(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('bloc-documents@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $service = new ServiceEvenementiel();
        $service->changeLabel('Service du bloc');
        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::CommercialSupport);
        $document->changeDamAssetId('asset-document');
        $document->changeLegende('Plaquette');
        $service->addRessource($document);
        $entityManager->persist($service);
        $entityManager->flush();
        $client->loginUser($user);

        // Métadonnées en AJAX : réponse JSON, pas de redirection ni de flash.
        $crawler = $client->request('GET', '/referentiel/services/fiche/'.$service->id().'/medias/bloc');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="service_document_metadata_'.$document->id().'"]')->form();
        $values = $form->getPhpValues();
        $values['service_document_metadata_'.$document->id()]['title'] = 'Plaquette 2026';
        $client->request($form->getMethod(), $form->getUri(), $values, [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"ok":true}', (string) $client->getResponse()->getContent());
        self::assertSame('Plaquette 2026', $this->connection->fetchOne('SELECT legende FROM pim_ressource_lieu'));

        // Sans le header, le fallback no-JS redirige vers la fiche (flash).
        $crawler = $client->request('GET', '/referentiel/services/fiche/'.$service->id().'/medias/bloc');
        $form = $crawler->filter('form[name="service_document_metadata_'.$document->id().'"]')->form();
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues());
        self::assertResponseRedirects();

        // Erreur de validation en AJAX : 422 + message, le document survit.
        $client->request('POST', '/referentiel/services/fiche/'.$service->id().'/documents/'.$document->id().'/modifier', [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(422);
        $retour = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($retour);
        self::assertArrayHasKey('error', $retour);

        // Suppression en AJAX avec le vrai formulaire du bloc.
        $crawler = $client->request('GET', '/referentiel/services/fiche/'.$service->id().'/medias/bloc');
        $form = $crawler->filter('form[name="service_document_delete_'.$document->id().'"]')->form();
        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseIsSuccessful();
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_ressource_lieu'));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_salle');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_acces');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
