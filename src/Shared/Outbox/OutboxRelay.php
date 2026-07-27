<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final readonly class OutboxRelay
{
    public function __construct(
        private OutboxRepository $repository,
        private Connection $connection,
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    public function relayBatch(string $workerId, int $limit = 100, int $leaseSeconds = 300): OutboxRelayResult
    {
        $claimed = 0;
        $published = 0;
        $failed = 0;

        while ($claimed < $limit && null !== $record = $this->repository->claimNext($workerId, $leaseSeconds)) {
            ++$claimed;

            try {
                $envelope = $this->serializer->decode([
                    'body' => $record->body,
                    'headers' => $this->decodeHeaders($record->headers),
                ]);

                $this->connection->transactional(function () use ($envelope, $record): void {
                    $this->messageBus->dispatch($envelope);
                    $this->repository->markPublished($record);
                });

                ++$published;
            } catch (\Throwable $error) {
                $this->repository->recordFailure($record, $error);
                $this->logger->error('Outbox message could not be relayed.', [
                    'event_id' => $record->id,
                    'message_type' => $record->messageType,
                    'exception' => $error,
                ]);
                ++$failed;
            }
        }

        return new OutboxRelayResult($claimed, $published, $failed);
    }

    /**
     * @return array<string, string>
     */
    private function decodeHeaders(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Outbox headers must decode to an array.');
        }

        $headers = [];
        foreach ($decoded as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new \UnexpectedValueException('Outbox headers must contain strings only.');
            }

            $headers[$name] = $value;
        }

        return $headers;
    }
}
