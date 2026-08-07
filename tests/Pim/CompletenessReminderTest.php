<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Completeness\CompletenessEntityResolver;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\FicheRelance;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Message\RemindIncompleteFiches;
use App\Pim\Message\SendFicheCompletenessReminder;
use App\Pim\MessageHandler\RemindIncompleteFichesHandler;
use App\Pim\MessageHandler\SendFicheCompletenessReminderHandler;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRelanceRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\CompletenessReminderMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class CompletenessReminderTest extends KernelTestCase
{
    private const int THRESHOLD = 60;
    private const int COOLDOWN_DAYS = 30;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testFanOutSelectsOnlyEligibleFiches(): void
    {
        $collaborateur = new FicheCollaborateur('presta@example.com', 'Paul', 'Presta');
        $inactive = new FicheCollaborateur('parti@example.com');
        $inactive->deactivate();
        $creator = new User('admin@businessprofilers.fr', ['ROLE_SUPER_ADMIN']);

        $eligible = $this->lieu('Fiche incomplète relançable');
        $noRecipient = $this->lieu('Fiche incomplète sans destinataire');
        $complete = $this->lieu('Fiche complète');
        $recentlyReminded = $this->lieu('Fiche relancée récemment');

        $this->entityManager->persist($creator);
        $this->entityManager->persist($collaborateur);
        $this->entityManager->persist($inactive);
        $this->entityManager->persist(new FicheAffiliation($collaborateur, $eligible->fiche(), FicheAffiliationRole::Manager, $creator, true));
        $this->entityManager->persist(new FicheAffiliation($inactive, $noRecipient->fiche(), FicheAffiliationRole::Manager, $creator, true));
        $this->entityManager->persist(new FicheAffiliation($collaborateur, $recentlyReminded->fiche(), FicheAffiliationRole::Manager, $creator, true));
        $this->entityManager->persist(new FicheRelance($recentlyReminded->fiche(), 30, ['presta@example.com']));
        $this->entityManager->flush();

        $connection = $this->entityManager->getConnection();
        foreach ([$eligible, $noRecipient, $recentlyReminded] as $lieu) {
            $connection->executeStatement('UPDATE pim_lieu SET completeness_global = 30 WHERE fiche_id = ?', [$lieu->fiche()->id()->toBinary()]);
        }
        $connection->executeStatement('UPDATE pim_lieu SET completeness_global = 90 WHERE fiche_id = ?', [$complete->fiche()->id()->toBinary()]);

        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
        $handler = new RemindIncompleteFichesHandler(
            self::getContainer()->get(FicheRelanceRepository::class),
            $bus,
            new NullLogger(),
            self::THRESHOLD,
            self::COOLDOWN_DAYS,
        );
        $handler(new RemindIncompleteFiches());

        $ficheIds = array_map(
            static fn (object $message): string => $message instanceof SendFicheCompletenessReminder ? $message->ficheId : '',
            $bus->messages,
        );
        self::assertSame([$eligible->fiche()->idString()], $ficheIds);
    }

    public function testSendHandlerMailsRecipientsAndLogsRelance(): void
    {
        $collaborateur = new FicheCollaborateur('presta@example.com', 'Paul', 'Presta');
        $anglophone = new FicheCollaborateur('partner@example.com', 'John', 'Partner', 'en');
        $creator = new User('admin@businessprofilers.fr', ['ROLE_SUPER_ADMIN']);
        $lieu = $this->lieu('Château des relances');
        $this->entityManager->persist($creator);
        $this->entityManager->persist($collaborateur);
        $this->entityManager->persist($anglophone);
        $this->entityManager->persist(new FicheAffiliation($collaborateur, $lieu->fiche(), FicheAffiliationRole::Manager, $creator, true));
        $this->entityManager->persist(new FicheAffiliation($anglophone, $lieu->fiche(), FicheAffiliationRole::Utilisateur, $creator, true));
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE pim_lieu SET completeness_global = 45 WHERE fiche_id = ?',
            [$lieu->fiche()->id()->toBinary()],
        );
        $this->entityManager->clear();

        $mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $messages = [];

            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $handler = $this->sendHandler($mailer);
        $handler(new SendFicheCompletenessReminder($lieu->fiche()->idString()));

        self::assertCount(2, $mailer->messages);
        $subjects = array_map(static fn (RawMessage $message): string => $message instanceof Email ? (string) $message->getSubject() : '', $mailer->messages);
        self::assertContains('[MDM] Votre fiche « Château des relances » est complétée à 45 %', $subjects);
        self::assertContains('[MDM] Your listing "Château des relances" is 45% complete', $subjects);

        $relances = self::getContainer()->get(FicheRelanceRepository::class)->findAll();
        self::assertCount(1, $relances);
        self::assertSame(45, $relances[0]->completenessAtSend());
        self::assertEqualsCanonicalizing(['presta@example.com', 'partner@example.com'], $relances[0]->recipientEmails());

        // Redélivrance : le cooldown bloque tout nouvel envoi.
        $handler(new SendFicheCompletenessReminder($lieu->fiche()->idString()));
        self::assertCount(2, $mailer->messages);
        self::assertCount(1, self::getContainer()->get(FicheRelanceRepository::class)->findAll());
    }

    private function sendHandler(MailerInterface $mailer): SendFicheCompletenessReminderHandler
    {
        return new SendFicheCompletenessReminderHandler(
            self::getContainer()->get(FicheRepository::class),
            self::getContainer()->get(FicheAffiliationRepository::class),
            self::getContainer()->get(FicheRelanceRepository::class),
            self::getContainer()->get(CompletenessEntityResolver::class),
            new CompletenessReminderMailer('noreply@businessprofilers.fr'),
            $mailer,
            $this->entityManager,
            self::THRESHOLD,
            self::COOLDOWN_DAYS,
        );
    }

    private function lieu(string $label): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $this->entityManager->persist($lieu);

        return $lieu;
    }

    private function clearTables(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach ([
            'pim_fiche_relance',
            'pim_fiche_affiliation',
            'pim_fiche_collaborateur',
            'pim_ressource_lieu',
            'pim_fiche_attribute_value',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
        ] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        $connection->executeStatement("DELETE FROM account_user WHERE email = 'admin@businessprofilers.fr'");
    }
}
