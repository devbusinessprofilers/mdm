<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Completeness\CompletenessEntityResolver;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\FicheRelance;
use App\Pim\Entity\FicheRelancePlanifiee;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\StatutRelancePlanifiee;
use App\Pim\Message\EnvoyerRelancePlanifiee;
use App\Pim\Message\EnvoyerRelancesPlanifiees;
use App\Pim\MessageHandler\EnvoyerRelancePlanifieeHandler;
use App\Pim\MessageHandler\EnvoyerRelancesPlanifieesHandler;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRelancePlanifieeRepository;
use App\Pim\Repository\FicheRelanceRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\CompletenessReminderMailer;
use App\Pim\Service\RelanceCompletudePlanificateur;
use App\Tests\Support\ParametresFixes;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

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

    public function testLaPreparationPlanifieSeulementLesFichesEligibles(): void
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

        $count = $this->planificateur()->preparer();

        self::assertSame(1, $count);
        $lot = self::getContainer()->get(FicheRelancePlanifieeRepository::class)->lotCourant();
        self::assertCount(1, $lot);
        self::assertSame($eligible->fiche()->idString(), $lot[0]->fiche()->idString());
        self::assertSame(30, $lot[0]->completenessAtPreparation());
        self::assertSame(['presta@example.com'], $lot[0]->recipientEmails());
        self::assertSame(StatutRelancePlanifiee::Planifiee, $lot[0]->statut());
    }

    public function testUneNouvellePreparationRemplaceLeLotPrecedent(): void
    {
        [$chateau, $moulin] = $this->deuxFichesEligibles();
        $planificateur = $this->planificateur();
        self::assertSame(2, $planificateur->preparer());

        $repository = self::getContainer()->get(FicheRelancePlanifieeRepository::class);
        $exclue = $this->ligneDeFiche($repository->lotCourant(), $chateau);
        $exclue->exclure();
        $this->entityManager->flush();

        // Le lot précédent est daté d'une heure plus tôt pour distinguer les
        // deux préparations (précision à la seconde du DATETIME).
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE pim_fiche_relance_planifiee SET prepared_at = DATE_SUB(prepared_at, INTERVAL 1 HOUR)',
        );
        $this->entityManager->clear();

        self::assertSame(2, $planificateur->preparer());
        $this->entityManager->clear();

        $statuts = [];
        foreach ($repository->findAll() as $planifiee) {
            $statuts[$planifiee->statut()->value] = ($statuts[$planifiee->statut()->value] ?? 0) + 1;
        }
        // Lot précédent : la ligne restée planifiée est annulée, l'exclue reste exclue.
        ksort($statuts);
        self::assertSame(['annulee' => 1, 'exclue' => 1, 'planifiee' => 2], $statuts);

        $lot = $repository->lotCourant();
        self::assertCount(2, $lot);
        foreach ($lot as $planifiee) {
            self::assertSame(StatutRelancePlanifiee::Planifiee, $planifiee->statut());
        }
    }

    public function testLeFanOutRespecteLeParametreDEnvoiAutomatique(): void
    {
        $this->deuxFichesEligibles();
        $this->planificateur()->preparer();

        $bus = $this->busEspion();
        $handlerOff = new EnvoyerRelancesPlanifieesHandler(
            self::getContainer()->get(FicheRelancePlanifieeRepository::class),
            $bus,
            new NullLogger(),
            new ParametresFixes(['completude.rappel_auto_actif' => '0']),
        );

        // Envoi auto désactivé : rien ne part, le lot reste planifié.
        $handlerOff(new EnvoyerRelancesPlanifiees());
        self::assertSame([], $bus->messages);
        foreach (self::getContainer()->get(FicheRelancePlanifieeRepository::class)->lotCourant() as $planifiee) {
            self::assertSame(StatutRelancePlanifiee::Planifiee, $planifiee->statut());
        }

        // « Envoyer maintenant » passe outre le paramètre.
        $handlerOff(new EnvoyerRelancesPlanifiees(force: true));
        self::assertCount(2, $bus->messages);
        self::assertContainsOnlyInstancesOf(EnvoyerRelancePlanifiee::class, $bus->messages);
    }

    public function testCycleCompletAvecExclusionEnvoiEtRevalidation(): void
    {
        [$chateau, $moulin] = $this->deuxFichesEligibles();
        $this->planificateur()->preparer();

        $repository = self::getContainer()->get(FicheRelancePlanifieeRepository::class);
        $this->ligneDeFiche($repository->lotCourant(), $moulin)->exclure();
        $this->entityManager->flush();

        // Le fan-out ne dispatch que les lignes encore planifiées.
        $bus = $this->busEspion();
        $fanOut = new EnvoyerRelancesPlanifieesHandler($repository, $bus, new NullLogger(), new ParametresFixes(['completude.rappel_auto_actif' => '1']));
        $fanOut(new EnvoyerRelancesPlanifiees());
        self::assertCount(1, $bus->messages);
        /** @var EnvoyerRelancePlanifiee $message */
        $message = $bus->messages[0];
        self::assertSame($this->ligneDeFiche($repository->lotCourant(), $chateau)->id(), $message->relancePlanifieeId);

        $this->entityManager->clear();
        $mailer = $this->mailerEspion();
        $handler = $this->envoiHandler($mailer);
        $handler($message);

        self::assertCount(2, $mailer->messages);
        $subjects = array_map(static fn (RawMessage $mail): string => $mail instanceof Email ? (string) $mail->getSubject() : '', $mailer->messages);
        self::assertContains('[MDM] Votre fiche « Château des relances » est complétée à 30 %', $subjects);
        self::assertContains('[MDM] Your listing "Château des relances" is 30% complete', $subjects);

        $relances = self::getContainer()->get(FicheRelanceRepository::class)->findAll();
        self::assertCount(1, $relances);
        self::assertSame(30, $relances[0]->completenessAtSend());
        self::assertEqualsCanonicalizing(['presta@example.com', 'partner@example.com'], $relances[0]->recipientEmails());
        self::assertSame(StatutRelancePlanifiee::Envoyee, $this->ligneDeFiche($repository->lotCourant(), $chateau)->statut());

        // Redélivrance : la garde de statut bloque tout nouvel envoi.
        $handler($message);
        self::assertCount(2, $mailer->messages);
        self::assertCount(1, self::getContainer()->get(FicheRelanceRepository::class)->findAll());
    }

    public function testUneFicheCompleteeEntreTempsEstIgnoreeALEnvoi(): void
    {
        [$chateau] = $this->deuxFichesEligibles();
        $this->planificateur()->preparer();

        $repository = self::getContainer()->get(FicheRelancePlanifieeRepository::class);
        $ligne = $this->ligneDeFiche($repository->lotCourant(), $chateau);
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE pim_lieu SET completeness_global = 95 WHERE fiche_id = ?',
            [$chateau->fiche()->id()->toBinary()],
        );
        $this->entityManager->clear();

        $mailer = $this->mailerEspion();
        $this->envoiHandler($mailer)(new EnvoyerRelancePlanifiee($ligne->id()));

        self::assertSame([], $mailer->messages);
        self::assertSame([], self::getContainer()->get(FicheRelanceRepository::class)->findAll());
        $ignoree = $this->ligneDeFiche($repository->lotCourant(), $chateau);
        self::assertSame(StatutRelancePlanifiee::Ignoree, $ignoree->statut());
        self::assertSame('Fiche complétée entre la préparation et l’envoi.', $ignoree->motif());
    }

    private function planificateur(): RelanceCompletudePlanificateur
    {
        return new RelanceCompletudePlanificateur(
            self::getContainer()->get(FicheRelanceRepository::class),
            self::getContainer()->get(FicheRelancePlanifieeRepository::class),
            self::getContainer()->get(FicheRepository::class),
            self::getContainer()->get(FicheAffiliationRepository::class),
            $this->entityManager,
            $this->parametres(),
        );
    }

    private function envoiHandler(MailerInterface $mailer): EnvoyerRelancePlanifieeHandler
    {
        return new EnvoyerRelancePlanifieeHandler(
            self::getContainer()->get(FicheRelancePlanifieeRepository::class),
            self::getContainer()->get(FicheAffiliationRepository::class),
            self::getContainer()->get(FicheRelanceRepository::class),
            self::getContainer()->get(CompletenessEntityResolver::class),
            new CompletenessReminderMailer('noreply@businessprofilers.fr'),
            $mailer,
            $this->entityManager,
            $this->parametres(),
        );
    }

    private function parametres(): ParametresFixes
    {
        return new ParametresFixes([
            'completude.seuil_rappel' => (string) self::THRESHOLD,
            'completude.delai_rappel_jours' => (string) self::COOLDOWN_DAYS,
        ]);
    }

    /**
     * Deux lieux éligibles à 30 % : « Château des relances » avec deux
     * destinataires (fr + en), « Moulin des relances » avec un seul.
     *
     * @return array{Lieu, Lieu}
     */
    private function deuxFichesEligibles(): array
    {
        $collaborateur = new FicheCollaborateur('presta@example.com', 'Paul', 'Presta');
        $anglophone = new FicheCollaborateur('partner@example.com', 'John', 'Partner', 'en');
        $creator = new User('admin@businessprofilers.fr', ['ROLE_SUPER_ADMIN']);
        $chateau = $this->lieu('Château des relances');
        $moulin = $this->lieu('Moulin des relances');
        $this->entityManager->persist($creator);
        $this->entityManager->persist($collaborateur);
        $this->entityManager->persist($anglophone);
        $this->entityManager->persist(new FicheAffiliation($collaborateur, $chateau->fiche(), FicheAffiliationRole::Manager, $creator, true));
        $this->entityManager->persist(new FicheAffiliation($anglophone, $chateau->fiche(), FicheAffiliationRole::Utilisateur, $creator, true));
        $this->entityManager->persist(new FicheAffiliation($collaborateur, $moulin->fiche(), FicheAffiliationRole::Manager, $creator, true));
        $this->entityManager->flush();
        foreach ([$chateau, $moulin] as $lieu) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE pim_lieu SET completeness_global = 30 WHERE fiche_id = ?',
                [$lieu->fiche()->id()->toBinary()],
            );
        }

        return [$chateau, $moulin];
    }

    /** @param list<FicheRelancePlanifiee> $lot */
    private function ligneDeFiche(array $lot, Lieu $lieu): FicheRelancePlanifiee
    {
        foreach ($lot as $planifiee) {
            if ($planifiee->fiche()->idString() === $lieu->fiche()->idString()) {
                return $planifiee;
            }
        }
        self::fail(sprintf('Aucune ligne planifiée pour la fiche « %s ».', $lieu->fiche()->label() ?? ''));
    }

    /** @return MessageBusInterface&object{messages: list<object>} */
    private function busEspion(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
    }

    /** @return MailerInterface&object{messages: list<RawMessage>} */
    private function mailerEspion(): MailerInterface
    {
        return new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $messages = [];

            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
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
            'pim_fiche_relance_planifiee',
            'pim_fiche_relance',
            'pim_fiche_affiliation',
            'pim_fiche_collaborateur',
            'pim_ressource_lieu',
            'pim_fiche_attribute_value',
            'pim_fiche_administratif',
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
