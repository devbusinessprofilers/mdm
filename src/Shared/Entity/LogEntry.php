<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne de log persistée par DoctrineDbalLogHandler pour la visionneuse de
 * /admin/performance : seul canal lisible par l'application dans tous les
 * environnements (en prod les fichiers n'existent pas, tout part en stderr).
 * level = valeur Monolog\Level (200 info, 300 warning, 400 error…) ; hostname
 * identifie le conteneur émetteur (workers compris).
 */
#[ORM\Entity]
#[ORM\Table(name: 'log_entry')]
#[ORM\Index(name: 'IDX_LOG_LEVEL_TS', columns: ['level', 'logged_at'])]
#[ORM\Index(name: 'IDX_LOG_CHANNEL_TS', columns: ['channel', 'logged_at'])]
#[ORM\Index(name: 'IDX_LOG_TS', columns: ['logged_at'])]
final class LogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /**
     * @param array<string, mixed>|null $context
     * @param array<string, mixed>|null $extra
     */
    public function __construct(
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $loggedAt,
        #[ORM\Column(length: 64)]
        private string $channel,
        #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
        private int $level,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::JSON, nullable: true)]
        private ?array $context = null,
        #[ORM\Column(type: Types::JSON, nullable: true)]
        private ?array $extra = null,
        #[ORM\Column(length: 36, nullable: true)]
        private ?string $requestId = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $hostname = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
