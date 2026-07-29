<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pim_attribute_definition')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_ATTRIBUTE_CODE', columns: ['code'])]
class AttributDefinition
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(options: ['unsigned' => true])]
        private int $id,
        #[ORM\Column(length: 64)]
        private string $code,
        #[ORM\Column(length: 255)]
        private string $label,
        #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
        private int $completenessWeight = 0,
        #[ORM\Column(options: ['default' => false])]
        private bool $translatable = false,
        #[ORM\Column(options: ['default' => true])]
        private bool $filterable = true,
        #[ORM\Column(length: 32, options: ['default' => 'pim'])]
        private string $masterApplication = 'pim',
    ) {
    }

    public function id(): int { return $this->id; }
    public function code(): string { return $this->code; }
    public function label(): string { return $this->label; }
}
