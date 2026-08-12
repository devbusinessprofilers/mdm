<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Message\SyncLovDictionary;
use App\Etl\Service\MarketplaceApiException;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceLovPayloadBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class SyncLovDictionaryHandler
{
    public function __construct(
        private MarketplaceLovPayloadBuilder $payloadBuilder,
        private MarketplaceClientInterface $client,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncLovDictionary $message): void
    {
        // Le dictionnaire est relu ici : un message en retard pousse l'état
        // courant, jamais un état intermédiaire.
        $sequence = (string) new Ulid();
        $payload = $this->payloadBuilder->build();
        $payload['sequence'] = $sequence;
        try {
            $applied = $this->client->upsertLovDictionary($payload);
        } catch (MarketplaceApiException $exception) {
            // Refus permanent (4xx) : relancer rejouerait le même échec.
            if (!$exception->isRetryable()) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
            }
            throw $exception;
        }
        if (!$applied) {
            $this->logger->notice('Dictionnaire LOV ignoré par la marketplace : elle détient déjà une séquence plus récente.', [
                'sequence' => $sequence,
            ]);
        }
    }
}
