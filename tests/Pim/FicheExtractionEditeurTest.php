<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\MediaKind;
use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Entity\OcrSuggestion;
use App\Ocr\Enum\SuggestionStatus;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Uid\Ulid;

/**
 * Extraction OCR depuis l'éditeur : dépôt, état « lecture en cours » bloquant
 * tout nouveau dépôt, puis validation des valeurs lues champ par champ.
 */
#[Group('database')]
final class FicheExtractionEditeurTest extends WebTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        // Le bloc extraction n'existe que lorsque l'OCR est activé.
        putenv('BOX_OCR_ENABLED=1');
        $_ENV['BOX_OCR_ENABLED'] = $_SERVER['BOX_OCR_ENABLED'] = '1';
    }

    protected function tearDown(): void
    {
        putenv('BOX_OCR_ENABLED');
        unset($_ENV['BOX_OCR_ENABLED'], $_SERVER['BOX_OCR_ENABLED']);
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testLeDepotEstBloqueTantQuUneLectureEstEnCours(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('extraction@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château à lire');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        // Temps 1 : sans lecture en cours, le formulaire de dépôt est là.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString(), ['section' => 15]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Extraire d\'un document');
        $bouton = $crawler->selectButton('Envoyer pour extraction');
        self::assertGreaterThan(0, $bouton->count());
        $formDepot = $bouton->form();

        // Une extraction part en file : l'éditeur passe en « lecture en cours ».
        $asset = new MediaAsset(new Ulid(), 'private/plaquette.pdf', 'plaquette.pdf', 'application/pdf', 123, str_repeat('a', 64), MediaKind::Document);
        $extraction = new DocumentExtraction($lieu->fiche(), $asset, 'PJ_PLAN_GENERAL', ['version' => 1, 'fields' => []], 'extraction@example.test');
        $entityManager->persist($asset);
        $entityManager->persist($extraction);
        $entityManager->flush();

        $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString(), ['section' => 15]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'En file');
        self::assertSelectorTextContains('body', 'Vous pourrez déposer un autre document quand cette lecture sera terminée.');
        self::assertSelectorNotExists('form[action$="/extraction/deposer"]');

        // Le dépôt soumis malgré tout est refusé par le mécanisme, pas par l'écran.
        $pdf = tempnam(sys_get_temp_dir(), 'ocr').'.pdf';
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $valeurs = $formDepot->getPhpValues();
        $client->request(
            $formDepot->getMethod(),
            $formDepot->getUri(),
            $valeurs,
            ['ocr_upload' => ['document' => new \Symfony\Component\HttpFoundation\File\UploadedFile($pdf, 'autre.pdf', 'application/pdf', null, true)]],
        );
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Une extraction est déjà en cours pour cette fiche');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ocr_document_extraction'));
        @unlink($pdf);
    }

    public function testLesValeursLuesSeValidentDepuisLEditeur(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('revue@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château lu');
        $entityManager->persist($lieu);

        $asset = new MediaAsset(new Ulid(), 'private/plaquette.pdf', 'plaquette.pdf', 'application/pdf', 123, str_repeat('b', 64), MediaKind::Document);
        $extraction = new DocumentExtraction($lieu->fiche(), $asset, 'PJ_PLAN_GENERAL', ['version' => 1, 'fields' => []], 'revue@example.test');
        $extraction->start(3);
        $extraction->complete([], null, null);
        $suggestion = new OcrSuggestion($extraction, 'fiche.label', 'Libellé', 'string', 'Château relu', 'Château lu', 0.9, [2]);
        $extraction->addSuggestion($suggestion);
        $entityManager->persist($asset);
        $entityManager->persist($extraction);
        $entityManager->persist($suggestion);
        $entityManager->flush();
        $client->loginUser($user);

        // Temps 3 : la revue est dans l'éditeur, avec page source et confiance.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString(), ['section' => 15]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Valider les valeurs lues');
        self::assertSelectorTextContains('table', 'Libellé');
        self::assertSelectorTextContains('table', 'p. 2');
        self::assertSelectorTextContains('table', '90 %');

        $form = $crawler->selectButton('Appliquer les décisions')->form();
        $reject = $form['ocr_review['.$suggestion->id().'][reject]'];
        if (!$reject instanceof ChoiceFormField) {
            self::fail('Le champ de rejet doit être une case à cocher.');
        }
        $reject->tick();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vos décisions ont été appliquées à la fiche.');

        $entityManager->clear();
        $rejouee = $entityManager->find(OcrSuggestion::class, $suggestion->id());
        self::assertInstanceOf(OcrSuggestion::class, $rejouee);
        self::assertSame(SuggestionStatus::Rejected, $rejouee->status());
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM ocr_suggestion');
        $this->connection->executeStatement('DELETE FROM ocr_document_extraction');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
