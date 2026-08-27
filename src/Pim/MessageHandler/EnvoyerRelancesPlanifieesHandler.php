<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Message\EnvoyerRelancePlanifiee;
use App\Pim\Message\EnvoyerRelancesPlanifiees;
use App\Pim\Repository\FicheRelancePlanifieeRepository;
use App\Shared\Service\ParametreProviderInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fan-out d'envoi du lot planifié : dispatch un message d'envoi (file mail)
 * par ligne encore planifiée. Le cron de 14h respecte le paramètre
 * completude.rappel_auto_actif ; le bouton « Envoyer maintenant » passe outre
 * (force). Un double fan-out est inoffensif : l'envoi unitaire ne traite que
 * les lignes au statut planifiée.
 */
#[WithMonologChannel('mail')]
#[AsMessageHandler]
final readonly class EnvoyerRelancesPlanifieesHandler
{
    public function __construct(
        private FicheRelancePlanifieeRepository $planifiees,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function __invoke(EnvoyerRelancesPlanifiees $message): void
    {
        if (!$message->force && !$this->parametres->bool('completude.rappel_auto_actif')) {
            $this->logger->info('Envoi automatique des relances de complétude désactivé, lot conservé.');

            return;
        }
        $ids = $this->planifiees->idsPlanifiees();
        foreach ($ids as $id) {
            $this->bus->dispatch(new EnvoyerRelancePlanifiee($id));
        }
        $this->logger->info('Envoi du lot de relances de complétude déclenché.', [
            'relances' => count($ids),
            'force' => $message->force,
        ]);
    }
}
