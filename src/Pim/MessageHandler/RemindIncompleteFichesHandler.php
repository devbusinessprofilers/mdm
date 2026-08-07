<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Message\RemindIncompleteFiches;
use App\Pim\Message\SendFicheCompletenessReminder;
use App\Pim\Repository\FicheRelanceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fan-out hebdomadaire : sélectionne en une requête les fiches sous le seuil
 * de complétude ayant au moins un destinataire de demandes actif et pas de
 * relance récente, puis dispatch un message d'envoi par fiche (file mail).
 */
#[AsMessageHandler]
final readonly class RemindIncompleteFichesHandler
{
    public function __construct(
        private FicheRelanceRepository $relances,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        #[Autowire(env: 'int:COMPLETENESS_REMINDER_THRESHOLD')]
        private int $threshold,
        #[Autowire(env: 'int:COMPLETENESS_REMINDER_COOLDOWN_DAYS')]
        private int $cooldownDays,
    ) {
    }

    public function __invoke(RemindIncompleteFiches $message): void
    {
        if ($this->threshold <= 0) {
            return;
        }
        $cooldownDate = new \DateTimeImmutable(sprintf('-%d days', max(1, $this->cooldownDays)));
        $ficheIds = $this->relances->findFicheIdsNeedingReminder($this->threshold, $cooldownDate);
        foreach ($ficheIds as $ficheId) {
            $this->bus->dispatch(new SendFicheCompletenessReminder($ficheId));
        }
        $this->logger->info('Relances de complétude planifiées.', [
            'fiches' => count($ficheIds),
            'threshold' => $this->threshold,
        ]);
    }
}
