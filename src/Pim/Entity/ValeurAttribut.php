<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\ValeurAttributRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ValeurAttributRepository::class)]
#[ORM\Table(name: 'pim_attribute_value')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_ATTRIBUTE_VALUE_CODE', columns: ['attribute_id', 'code'])]
#[ORM\Index(name: 'IDX_PIM_ATTRIBUTE_VALUES', columns: ['attribute_id', 'position', 'id'])]
class ValeurAttribut
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(options: ['unsigned' => true])]
        private int $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private AttributDefinition $attribute,
        #[ORM\Column(length: 96)]
        private string $code,
        #[ORM\Column(length: 255)]
        private string $label,
        #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
        private int $position = 0,
        #[ORM\Column(options: ['default' => true])]
        private bool $active = true,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function attribute(): AttributDefinition
    {
        return $this->attribute;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function changeLabel(string $label): void
    {
        $this->label = trim($label);
    }

    public function changePosition(int $position): void
    {
        $this->position = max(0, $position);
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
