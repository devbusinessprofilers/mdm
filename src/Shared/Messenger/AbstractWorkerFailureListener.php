<?php

declare(strict_types=1);

namespace App\Shared\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Squelette commun des écouteurs qui marquent une entité de suivi en échec
 * quand un message asynchrone a épuisé ses relances.
 *
 * Il porte ce que chaque module réécrivait : le filtre sur la dernière
 * tentative, la remise en état d'un EntityManager fermé par l'exception du
 * handler, le flush, et la garde de dernier recours — un écouteur d'échec ne
 * relance jamais, sinon l'alerte et la trace des échecs qui suivent ne
 * s'exécutent pas. Les sous-classes ne disent que « quel message » et
 * « quelle entité marquer ». Poser `#[AsEventListener]` sur la sous-classe.
 */
abstract readonly class AbstractWorkerFailureListener
{
    public function __construct(
        private ManagerRegistry $registry,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$this->concerne($message)) {
            return;
        }
        if ($event->willRetry() && !$this->agitAussiPendantLesRelances()) {
            return;
        }
        try {
            $manager = $this->manager();
            $this->marquer($manager, $message, $event);
            $manager->flush();
        } catch (\Throwable $error) {
            $this->logger->error('Impossible de marquer l’échec du message.', [
                'message_class' => $message::class,
                'exception' => $error,
            ]);
        }
    }

    /** Vrai pour les messages dont cet écouteur tient le suivi. */
    abstract protected function concerne(object $message): bool;

    /** Marque l'entité de suivi (persist compris s'il le faut) ; le flush est fait par le squelette. */
    abstract protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void;

    /** Par défaut, seul l'échec définitif (relances épuisées) est traité. */
    protected function agitAussiPendantLesRelances(): bool
    {
        return false;
    }

    /** L'EntityManager courant, réinitialisé s'il a été fermé par l'exception du handler. */
    private function manager(): EntityManagerInterface
    {
        $manager = $this->registry->getManager();
        if (!$manager instanceof EntityManagerInterface || !$manager->isOpen()) {
            $manager = $this->registry->resetManager();
        }
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException('Le gestionnaire Doctrine n’est pas un EntityManager.');
        }

        return $manager;
    }
}
