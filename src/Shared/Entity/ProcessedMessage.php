<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'processed_message')]
#[ORM\Index(name: 'IDX_PROCESSED_MESSAGE_DATE', columns: ['processed_at'])]
final class ProcessedMessage
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 26)]
        private string $eventId,
        #[ORM\Column(length: 255)]
        private string $messageType,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $processedAt,
    ) {
    }
}
