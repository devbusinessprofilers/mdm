<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\CompletenessConfigurationAuditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CompletenessConfigurationAuditRepository::class)]
#[ORM\Table(name: 'pim_completeness_configuration_audit')]
#[ORM\Index(name: 'IDX_COMPLETENESS_AUDIT_FIELD_DATE', columns: ['fiche_type', 'field_code', 'changed_at'])]
final class CompletenessConfigurationAudit
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    /** @param array<string, mixed>|null $before
     *  @param array<string, mixed>|null $after
     */
    public function __construct(
        #[ORM\Column(name: 'fiche_type', length: 32, enumType: TypeFiche::class)]
        private TypeFiche $ficheType,
        #[ORM\Column(name: 'field_code', length: 96)]
        private string $fieldCode,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $revision,
        #[ORM\Column(length: 180)]
        private string $actor,
        #[ORM\Column(length: 32)]
        private string $source,
        #[ORM\Column(name: 'before_value', type: Types::JSON, nullable: true)]
        private ?array $before,
        #[ORM\Column(name: 'after_value', type: Types::JSON, nullable: true)]
        private ?array $after,
    ) {
        $this->id = new Ulid();
        $this->fieldCode = strtoupper(trim($fieldCode));
        $this->actor = trim($actor);
        $this->changedAt = new \DateTimeImmutable();
    }

    #[ORM\Column(name: 'changed_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    public function id(): string { return (string) $this->id; }
    public function ficheType(): TypeFiche { return $this->ficheType; }
    public function fieldCode(): string { return $this->fieldCode; }
    public function revision(): int { return $this->revision; }
    public function actor(): string { return $this->actor; }
    public function source(): string { return $this->source; }
    /** @return array<string, mixed>|null */
    public function before(): ?array { return $this->before; }
    /** @return array<string, mixed>|null */
    public function after(): ?array { return $this->after; }
    public function changedAt(): \DateTimeImmutable { return $this->changedAt; }
}
