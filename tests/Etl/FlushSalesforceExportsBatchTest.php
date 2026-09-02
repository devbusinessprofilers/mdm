<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Dashboard\Repository\JournalTraitementsRepository;
use App\Etl\Entity\FicheSalesforceExport;
use App\Etl\Message\FlushSalesforceExports;
use App\Etl\MessageHandler\FlushSalesforceExportsHandler;
use App\Etl\Repository\FicheSalesforceExportRepository;
use App\Etl\Service\SalesforceCsvMailer;
use App\Etl\Service\SalesforceCsvSettings;
use App\Etl\Service\SalesforceProduitsCsvExporter;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use App\Tests\Support\ParametresFixes;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Uid\Ulid;

/**
 * Envoi Produits groupé : un tic sert toutes les fiches en attente dans une
 * poignée d'e-mails multi-lignes — plus un e-mail par fiche.
 */
#[Group('database')]
final class FlushSalesforceExportsBatchTest extends KernelTestCase
{
    private MailerEnregistreur $mailer;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->clearTables();
        $this->mailer = new MailerEnregistreur();
    }

    protected function tearDown(): void
    {
        $this->clearTables();
        parent::tearDown();
    }

    public function testUnTicEnvoieUnSeulEmailPourToutesLesFichesEnAttente(): void
    {
        $fiches = [$this->fiche('Alpha'), $this->fiche('Bravo'), $this->fiche('Charlie')];
        foreach ($fiches as $fiche) {
            $this->marquerDirty($fiche);
        }

        ($this->handler())(new FlushSalesforceExports());

        self::assertCount(1, $this->mailer->messages);
        $email = $this->mailer->messages[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('integration=jeton-test;interface=Produits', $email->getSubject());
        // En-têtes + une ligne par fiche dans la même pièce jointe.
        self::assertSame(4, substr_count((string) $email->getAttachments()[0]->getBody(), "\r\n"));

        // Toutes marquées envoyées : un second tic ne renvoie rien.
        ($this->handler())(new FlushSalesforceExports());
        self::assertCount(1, $this->mailer->messages);

        // Le journal /outils les montre traitées.
        $journal = self::getContainer()->get(JournalTraitementsRepository::class)->journal('salesforce');
        self::assertCount(3, $journal);
        self::assertSame(['termine'], array_values(array_unique(array_column($journal, 'statut'))));
    }

    public function testUnEchecDuTransportMetToutLePaquetEnBackoff(): void
    {
        $fiche = $this->fiche('Delta');
        $this->marquerDirty($fiche);
        $this->mailer->panne = true;

        ($this->handler())(new FlushSalesforceExports());

        $export = $this->exports()->forFiche($fiche->id());
        self::assertInstanceOf(FicheSalesforceExport::class, $export);
        self::assertSame(1, $export->failureCount());
        self::assertNotNull($export->retryAt());
        // En backoff : le tic suivant ne retente pas tout de suite.
        $this->mailer->panne = false;
        ($this->handler())(new FlushSalesforceExports());
        self::assertSame([], $this->mailer->messages);
        // Et le journal le signale en erreur.
        $echecs = self::getContainer()->get(JournalTraitementsRepository::class)->journal('salesforce', seulementErreurs: true);
        self::assertCount(1, $echecs);
    }

    public function testUneFicheSupprimeePurgeSonSuivi(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $fantome = new FicheSalesforceExport(new Ulid(), 999999);
        $entityManager->persist($fantome);
        $entityManager->flush();

        ($this->handler())(new FlushSalesforceExports());

        self::assertSame([], $this->mailer->messages);
        self::assertSame(0, (int) self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM etl_fiche_salesforce_export'));
    }

    private function handler(): FlushSalesforceExportsHandler
    {
        $settings = new SalesforceCsvSettings(new ParametresFixes([
            'salesforce.csv_actif' => '1',
            'salesforce.csv_destinataire' => 'sf@salesforce.example',
            'salesforce.csv_expediteur' => 'expediteur@bp.fr',
            'salesforce.csv_token' => 'jeton-test',
        ]), 'defaut@bp.fr');

        return new FlushSalesforceExportsHandler(
            $this->exports(),
            self::getContainer()->get(FicheRepository::class),
            self::getContainer()->get(SalesforceProduitsCsvExporter::class),
            new SalesforceCsvMailer($this->mailer, $settings, new NullLogger()),
            self::getContainer()->get(EntityManagerInterface::class),
            new NullLogger(),
        );
    }

    private function exports(): FicheSalesforceExportRepository
    {
        return self::getContainer()->get(FicheSalesforceExportRepository::class);
    }

    private function fiche(string $label): Fiche
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $activite = new Activite();
        $activite->changeLabel($label);
        $entityManager->persist($activite);
        $entityManager->flush();

        return $activite->fiche();
    }

    private function marquerDirty(Fiche $fiche): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $export = new FicheSalesforceExport($fiche->id(), $fiche->code());
        $export->markDirty();
        $entityManager->persist($export);
        $entityManager->flush();
    }

    private function clearTables(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        foreach (['outbox_message', 'etl_fiche_salesforce_export', 'pim_fiche_search', 'pim_activite', 'pim_fiche', 'pim_localisation'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
    }
}

/** Double de test enregistrant tous les messages, avec panne simulable. */
final class MailerEnregistreur implements MailerInterface
{
    /** @var list<RawMessage> */
    public array $messages = [];
    public bool $panne = false;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->panne) {
            throw new \RuntimeException('SMTP indisponible (simulé).');
        }
        $this->messages[] = $message;
    }
}
