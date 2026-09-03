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

        // Temps 3 : la revue vit dans le bloc « Suggestions en attente » au pied
        // de l'éditeur, avec page source et confiance.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString(), ['section' => 15]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-suggestions-attente]', 'Valider les valeurs lues');
        self::assertSelectorTextContains('[data-suggestions-attente]', 'valeur lue (corrigible');
        self::assertSelectorTextContains('[data-suggestions-attente]', 'p. 2');
        self::assertSelectorTextContains('[data-suggestions-attente]', '90 %');

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

    public function testLApplicationAutomatiqueRespecteLeSeuilParametre(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $lieu = new Lieu();
        $lieu->changeLabel('Château manuel');
        $lieu->fiche()->publishForImport();
        $entityManager->persist($lieu);
        $asset = new MediaAsset(new Ulid(), 'private/plaquette.pdf', 'plaquette.pdf', 'application/pdf', 123, str_repeat('d', 64), MediaKind::Document);
        $extraction = new DocumentExtraction($lieu->fiche(), $asset, 'PJ_PLAN_GENERAL', ['version' => 1, 'fields' => []], 'auto@example.test');
        $extraction->start(1);
        $extraction->complete([], null, null);
        $sure = new OcrSuggestion($extraction, 'fiche.label', 'Libellé', 'string', 'Château automatique', 'Château manuel', 0.93, [1]);
        $douteuse = new OcrSuggestion($extraction, 'champ.inconnu', 'Champ incertain', 'string', 'valeur incertaine', null, 0.42, [1]);
        $entityManager->persist($asset);
        $entityManager->persist($extraction);
        $entityManager->persist($sure);
        $entityManager->persist($douteuse);
        $entityManager->flush();

        $handler = self::getContainer()->get(\App\Ocr\MessageHandler\AutoApplyOcrSuggestionsHandler::class);
        $provider = self::getContainer()->get(\App\Shared\Service\ParametreProvider::class);

        // Seuil à 0 (défaut) : rien ne bouge, tout reste manuel.
        $handler(new \App\Ocr\Message\AutoApplyOcrSuggestions($extraction->id()));
        self::assertSame('pending', $this->connection->fetchOne('SELECT status FROM ocr_suggestion WHERE id = ?', [Ulid::fromString($sure->id())->toBinary()]));

        // Seuil surchargé en table : la suggestion sûre s'applique, la
        // douteuse attend, la fiche publiée le reste (pas de transition).
        \App\Tests\Support\ParametreEnBase::fixer($this->connection, 'ocr.seuil_application_auto', '85', \App\Shared\Enum\TypeParametre::Entier);
        $provider->invalider();
        $handler(new \App\Ocr\Message\AutoApplyOcrSuggestions($extraction->id()));

        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, (string) $lieu->id());
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame('Château automatique', $recharge->label());
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
        $decidee = $entityManager->find(OcrSuggestion::class, $sure->id());
        self::assertInstanceOf(OcrSuggestion::class, $decidee);
        self::assertSame(SuggestionStatus::Accepted, $decidee->status());
        self::assertStringContainsString('automatique', (string) $decidee->decidedBy());
        $attente = $entityManager->find(OcrSuggestion::class, $douteuse->id());
        self::assertInstanceOf(OcrSuggestion::class, $attente);
        self::assertSame(SuggestionStatus::Pending, $attente->status());

        \App\Tests\Support\ParametreEnBase::fixer($this->connection, 'ocr.seuil_application_auto', null, \App\Shared\Enum\TypeParametre::Entier);
        $provider->invalider();
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
