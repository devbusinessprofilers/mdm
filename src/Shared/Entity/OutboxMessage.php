<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use App\Shared\Outbox\OutboxStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'outbox_message')]
#[ORM\Index(name: 'IDX_OUTBOX_PENDING', columns: ['status', 'available_at', 'occurred_at'])]
#[ORM\Index(name: 'IDX_OUTBOX_LOCKED', columns: ['locked_at'])]
#[ORM\Index(name: 'IDX_OUTBOX_PUBLISHED', columns: ['published_at'])]
final class OutboxMessage
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 26)]
        private string $id,
        #[ORM\Column(length: 255)]
        private string $messageType,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
        #[ORM\Column(type: Types::JSON)]
        private array $headers,
        #[ORM\Column(length: 16, enumType: OutboxStatus::class)]
        private OutboxStatus $status,
        #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
        private int $attempts,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $availableAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $lockedAt = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $lockedBy = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $publishedAt = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $lastError = null,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function pending(
        string $id,
        string $messageType,
        string $body,
        array $headers,
        \DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            id: $id,
            messageType: $messageType,
            body: $body,
            headers: $headers,
            status: OutboxStatus::Pending,
            attempts: 0,
            occurredAt: $occurredAt,
            availableAt: $occurredAt,
        );
    }

    public function id(): string
    {
        return $this->id;
    }
}
