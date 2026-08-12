<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Completeness\CompletenessEntityResolver;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheRelance;
use App\Pim\Enum\StatutFiche;
use App\Pim\Message\SendFicheCompletenessReminder;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRelanceRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\CompletenessReminderMailer;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

/**
 * Envoi effectif d'une relance : revalide seuil, statut et cooldown au moment
 * de la consommation (idempotence en cas de redélivrance ou de fiche complétée
 * entre-temps), envoie un email par collaborateur destinataire actif, puis
 * journalise dans pim_fiche_relance.
 */
#[AsMessageHandler]
final readonly class SendFicheCompletenessReminderHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheAffiliationRepository $affiliations,
        private FicheRelanceRepository $relances,
        private CompletenessEntityResolver $resolver,
        private CompletenessReminderMailer $reminderMailer,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function __invoke(SendFicheCompletenessReminder $message): void
    {
        if (!Ulid::isValid($message->ficheId)) {
            return;
        }
        $fiche = $this->fiches->find(Ulid::fromString($message->ficheId));
        if (!$fiche instanceof Fiche || StatutFiche::Archivee === $fiche->status()) {
            return;
        }
        $lastSentAt = $this->relances->lastSentAt($fiche);
        $cooldownDays = $this->parametres->int('completude.delai_rappel_jours');
        if (null !== $lastSentAt && $lastSentAt > new \DateTimeImmutable(sprintf('-%d days', max(1, $cooldownDays)))) {
            return;
        }
        $detail = $this->resolver->resolve($fiche);
        if (null === $detail || !method_exists($detail, 'completeness')) {
            return;
        }
        $score = $detail->completeness();
        if (!is_int($score) || $score >= $this->parametres->int('completude.seuil_rappel')) {
            // La fiche a été complétée entre la sélection et l'envoi.
            return;
        }
        $recipients = array_values(array_filter(
            $this->affiliations->findBy(['fiche' => $fiche, 'receivesRequests' => true]),
            static fn (FicheAffiliation $affiliation): bool => $affiliation->collaborateur()->isActive(),
        ));
        if ([] === $recipients) {
            return;
        }
        $emails = [];
        foreach ($recipients as $affiliation) {
            $collaborateur = $affiliation->collaborateur();
            $this->mailer->send($this->reminderMailer->build($collaborateur, $fiche, $score));
            $emails[] = $collaborateur->email();
        }
        $this->entityManager->persist(new FicheRelance($fiche, $score, $emails));
        $this->entityManager->flush();
    }
}
