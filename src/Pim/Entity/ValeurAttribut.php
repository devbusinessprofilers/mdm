<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
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
    ) {
    }

    public function id(): int { return $this->id; }
    public function attribute(): AttributDefinition { return $this->attribute; }
    public function code(): string { return $this->code; }
    public function label(): string { return $this->label; }
}
