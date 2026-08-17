<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\MediaKind;
use App\Dashboard\Repository\FilesATraiterRepository;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Etl\Entity\FicheImportJob;
use App\Etl\Entity\FicheMarketplaceSync;
use App\Ocr\Entity\DocumentExtraction;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class TraitementsEnEchecControllerTest extends WebTestCase
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

    public function testLaVueEstReserveeAuxAdmins(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/traitements-en-echec');
        self::assertResponseRedirects('http://localhost/connexion');

        $client->loginUser($this->persistUser('validator-echecs@example.test', ['ROLE_BP_VALIDATOR']));
        $client->request('GET', '/admin/traitements-en-echec');
        self::assertResponseStatusCodeSame(403);
    }

    public function testListeLesEchecsAvecLeursMessagesEtLeursLiens(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $this->persistUser('admin-echecs@example.test', ['ROLE_ADMIN']);

        $lieu = new Lieu();
        $lieu->changeLabel('Château des échecs');
        $entityManager->persist($lieu);

        $import = new FicheImportJob(TypeFiche::Lieu, 'import-echec.csv', 'tester');
        $import->fail('Fichier illisible : colonne code absente');
        $entityManager->persist($import);

        $document = new MediaAsset(new Ulid(), 'private/plaquette.pdf', 'plaquette.pdf', 'application/pdf', 123, str_repeat('a', 64), MediaKind::Document);
        $extraction = new DocumentExtraction($lieu->fiche(), $document, 'PJ_PLAN_GENERAL', ['version' => 1, 'fields' => []], 'tester');
        $extraction->fail('OCR indisponible : quota du fournisseur épuisé');
        $entityManager->persist($document);
        $entityManager->persist($extraction);

        $media = new MediaAsset(new Ulid(), 'originals/photo.jpg', 'photo-echec.jpg', 'image/jpeg', 456, str_repeat('b', 64));
        $media->markFailed('Conversion impossible : image corrompue');
        $entityManager->persist($media);

        $traduction = new FicheTranslation($lieu->fiche(), 'nom', 'Nom', SupportedLocale::En, 'Château des échecs');
        $entityManager->persist($traduction);
        $entityManager->flush();
        // fail() exige le jeton de la demande en cours : on force l'état en base.
        $this->connection->executeStatement(
            "UPDATE enrichment_fiche_translation SET status = 'en_erreur', last_error = 'Google Translate a répondu 500' WHERE id = ?",
            [Ulid::fromString($traduction->id())->toBinary()],
        );

        $marketplace = new FicheMarketplaceSync($lieu->fiche()->id(), $lieu->fiche()->code());
        $marketplace->recordFailure('La marketplace a refusé la fiche (HTTP 422)');
        $entityManager->persist($marketplace);
        $entityManager->flush();

        $this->connection->executeStatement(
            "INSERT INTO outbox_message (id, message_type, body, headers, status, attempts, occurred_at, available_at, last_error)
             VALUES (?, ?, '{}', '{}', 'failed', 5, NOW(), NOW(), 'Relais impossible : transport indisponible')",
            [(string) new Ulid(), 'App\\Shared\\Message\\MediaUploaded'],
        );

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/traitements-en-echec');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Traitements en échec');
        $contenu = $crawler->filter('main')->text(null, true);
        self::assertStringContainsString('Fichier illisible : colonne code absente', $contenu);
        self::assertStringContainsString('OCR indisponible : quota du fournisseur épuisé', $contenu);
        self::assertStringContainsString('Conversion impossible : image corrompue', $contenu);
        self::assertStringContainsString('Google Translate a répondu 500', $contenu);
        self::assertStringContainsString('La marketplace a refusé la fiche (HTTP 422)', $contenu);
        self::assertStringContainsString('Relais impossible : transport indisponible', $contenu);

        // Le périmètre de la vue est celui du compteur du tableau de bord.
        $comptes = self::getContainer()->get(FilesATraiterRepository::class)->comptes();
        self::assertSame(6, $comptes['echecs']);
        self::assertSame($comptes['echecs'], $crawler->filter('table tbody tr')->count());

        // Chaque ligne pointe vers l'écran de détail où vit la relance.
        $ficheId = $lieu->fiche()->idString();
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/import-fiches/'.$import->idString().'"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/referentiel/fiche/'.$ficheId.'/ocr/'.$extraction->id().'"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/referentiel/fiche/'.$ficheId.'/traductions"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/dam?filter=failed"]')->count());
    }

    public function testLeTableauDeBordReserveLesActionsTraiterAuxAdmins(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('validator-tdb@example.test', ['ROLE_BP_VALIDATOR']));
        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        // Les cartes de file sont les liens ; celle des échecs (route admin)
        // n'est cliquable que pour les admins.
        self::assertSame(0, $crawler->filter('a[href="/admin/traitements-en-echec"]')->count());

        $client->loginUser($this->persistUser('admin-tdb@example.test', ['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/traitements-en-echec"]')->count());
    }

    /** @param list<string> $roles */
    private function persistUser(string $email, array $roles = []): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User($email, $roles);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM ocr_suggestion');
        $this->connection->executeStatement('DELETE FROM ocr_document_extraction');
        $this->connection->executeStatement('DELETE FROM enrichment_fiche_translation');
        $this->connection->executeStatement('DELETE FROM etl_import_job_error');
        $this->connection->executeStatement('DELETE FROM etl_import_job');
        $this->connection->executeStatement('DELETE FROM etl_fiche_marketplace');
        $this->connection->executeStatement('DELETE FROM dam_media_rendition');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
