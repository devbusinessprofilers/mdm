<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Liste et actions sur la DLQ Messenger (transport failed) pour la page
 * /admin/performance : équivalents web de messenger:failed:show / retry /
 * remove. Le retry renvoie le message nu vers son transport d'origine
 * (TransportNamesStamp) : compteur de tentatives remis à zéro, traitement par
 * le worker habituel plutôt qu'en synchrone dans la requête web.
 */
final readonly class FailedMessageActions
{
    public function __construct(
        // TransportInterface plutôt que ListableReceiverInterface : en test le
        // transport failed est in-memory et n'est pas listable — la page doit
        // dégrader en liste vide, pas casser.
        #[Autowire(service: 'messenger.transport.failed')]
        private TransportInterface $failed,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @return list<array{id: mixed, classe: string, transport: ?string, erreur: ?string, echoue_le: ?\DateTimeInterface}>
     */
    public function lister(int $limite = 50): array
    {
        if (!$this->failed instanceof ListableReceiverInterface) {
            return [];
        }
        $lignes = [];
        try {
            foreach ($this->failed->all($limite) as $envelope) {
                $lignes[] = $this->decrire($envelope);
            }
        } catch (\Throwable) {
            // Table messenger_messages absente (base neuve) : liste vide.
        }

        return $lignes;
    }

    public function compter(): int
    {
        try {
            return $this->failed instanceof MessageCountAwareInterface
                ? $this->failed->getMessageCount()
                : count($this->lister(1000));
        } catch (\Throwable) {
            return 0;
        }
    }

    public function reessayer(string $id): bool
    {
        $envelope = $this->trouver($id);
        if (null === $envelope) {
            return false;
        }
        $transport = $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName();
        $this->bus->dispatch(
            $envelope->getMessage(),
            null !== $transport ? [new TransportNamesStamp([$transport])] : [],
        );
        $this->failed->reject($envelope);

        return true;
    }

    public function supprimer(string $id): bool
    {
        $envelope = $this->trouver($id);
        if (null === $envelope) {
            return false;
        }
        $this->failed->reject($envelope);

        return true;
    }

    private function trouver(string $id): ?Envelope
    {
        if (!$this->failed instanceof ListableReceiverInterface) {
            return null;
        }
        try {
            return $this->failed->find($id);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{id: mixed, classe: string, transport: ?string, erreur: ?string, echoue_le: ?\DateTimeInterface}
     */
    private function decrire(Envelope $envelope): array
    {
        $redelivery = $envelope->last(RedeliveryStamp::class);

        return [
            'id' => $envelope->last(TransportMessageIdStamp::class)?->getId(),
            'classe' => $envelope->getMessage()::class,
            'transport' => $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName(),
            'erreur' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
            'echoue_le' => $redelivery?->getRedeliveredAt(),
        ];
    }
}
