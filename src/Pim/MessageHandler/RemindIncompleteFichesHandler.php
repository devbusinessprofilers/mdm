<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Message\RemindIncompleteFiches;
use App\Pim\Message\SendFicheCompletenessReminder;
use App\Pim\Repository\FicheRelanceRepository;
use App\Shared\Service\ParametreProviderInterface;
use Psr\Log\LoggerInterface;
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
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function __invoke(RemindIncompleteFiches $message): void
    {
        $threshold = $this->parametres->int('completude.seuil_rappel');
        if ($threshold <= 0) {
            return;
        }
        $cooldownDays = $this->parametres->int('completude.delai_rappel_jours');
        $cooldownDate = new \DateTimeImmutable(sprintf('-%d days', max(1, $cooldownDays)));
        $ficheIds = $this->relances->findFicheIdsNeedingReminder($threshold, $cooldownDate);
        foreach ($ficheIds as $ficheId) {
            $this->bus->dispatch(new SendFicheCompletenessReminder($ficheId));
        }
        $this->logger->info('Relances de complétude planifiées.', [
            'fiches' => count($ficheIds),
            'threshold' => $threshold,
        ]);
    }
}
