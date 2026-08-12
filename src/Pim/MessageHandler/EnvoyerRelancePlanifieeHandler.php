<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Completeness\CompletenessEntityResolver;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheRelance;
use App\Pim\Entity\FicheRelancePlanifiee;
use App\Pim\Enum\StatutFiche;
use App\Pim\Message\EnvoyerRelancePlanifiee;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRelancePlanifieeRepository;
use App\Pim\Repository\FicheRelanceRepository;
use App\Pim\Service\CompletenessReminderMailer;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

/**
 * Envoi effectif d'une ligne du lot planifié. La garde de statut (seules les
 * lignes encore planifiées sont traitées) rend l'envoi idempotent en cas de
 * double fan-out ou de redélivrance, et protège les lignes exclues par un
 * admin. Statut, cooldown, score et destinataires sont revalidés au moment de
 * l'envoi (le snapshot de la préparation ne sert qu'à l'affichage) ; une
 * revalidation négative passe la ligne en ignorée avec son motif, visible sur
 * le dashboard. Les envois restent journalisés dans pim_fiche_relance.
 */
#[AsMessageHandler]
final readonly class EnvoyerRelancePlanifieeHandler
{
    public function __construct(
        private FicheRelancePlanifieeRepository $planifiees,
        private FicheAffiliationRepository $affiliations,
        private FicheRelanceRepository $relances,
        private CompletenessEntityResolver $resolver,
        private CompletenessReminderMailer $reminderMailer,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function __invoke(EnvoyerRelancePlanifiee $message): void
    {
        if (!Ulid::isValid($message->relancePlanifieeId)) {
            return;
        }
        $planifiee = $this->planifiees->find(Ulid::fromString($message->relancePlanifieeId));
        if (!$planifiee instanceof FicheRelancePlanifiee || !$planifiee->estPlanifiee()) {
            return;
        }
        $fiche = $planifiee->fiche();
        if (StatutFiche::Archivee === $fiche->status()) {
            $this->ignorer($planifiee, 'Fiche archivée entre la préparation et l’envoi.');

            return;
        }
        $lastSentAt = $this->relances->lastSentAt($fiche);
        $cooldownDays = $this->parametres->int('completude.delai_rappel_jours');
        if (null !== $lastSentAt && $lastSentAt > new \DateTimeImmutable(sprintf('-%d days', max(1, $cooldownDays)))) {
            $this->ignorer($planifiee, 'Relance déjà envoyée pendant le délai minimal.');

            return;
        }
        $detail = $this->resolver->resolve($fiche);
        if (null === $detail || !method_exists($detail, 'completeness')) {
            $this->ignorer($planifiee, 'Score de complétude indisponible.');

            return;
        }
        $score = $detail->completeness();
        if (!is_int($score) || $score >= $this->parametres->int('completude.seuil_rappel')) {
            $this->ignorer($planifiee, 'Fiche complétée entre la préparation et l’envoi.');

            return;
        }
        $recipients = array_values(array_filter(
            $this->affiliations->findBy(['fiche' => $fiche, 'receivesRequests' => true]),
            static fn (FicheAffiliation $affiliation): bool => $affiliation->collaborateur()->isActive(),
        ));
        if ([] === $recipients) {
            $this->ignorer($planifiee, 'Plus aucun destinataire actif.');

            return;
        }
        $emails = [];
        foreach ($recipients as $affiliation) {
            $collaborateur = $affiliation->collaborateur();
            $this->mailer->send($this->reminderMailer->build($collaborateur, $fiche, $score));
            $emails[] = $collaborateur->email();
        }
        $this->entityManager->persist(new FicheRelance($fiche, $score, $emails));
        $planifiee->marquerEnvoyee();
        $this->entityManager->flush();
    }

    private function ignorer(FicheRelancePlanifiee $planifiee, string $motif): void
    {
        $planifiee->marquerIgnoree($motif);
        $this->entityManager->flush();
    }
}
