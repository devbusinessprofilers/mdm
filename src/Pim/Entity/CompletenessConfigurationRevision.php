<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\CompletenessConfigurationRevisionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompletenessConfigurationRevisionRepository::class)]
#[ORM\Table(name: 'pim_completeness_configuration_revision')]
class CompletenessConfigurationRevision
{
    #[ORM\Id]
    #[ORM\Column(name: 'fiche_type', length: 32, enumType: TypeFiche::class)]
    private TypeFiche $ficheType;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 1])]
    private int $revision = 1;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(TypeFiche $type)
    {
        $this->ficheType = $type;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function ficheType(): TypeFiche { return $this->ficheType; }
    public function revision(): int { return $this->revision; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function increment(): int
    {
        ++$this->revision;
        $this->updatedAt = new \DateTimeImmutable();

        return $this->revision;
    }
}
