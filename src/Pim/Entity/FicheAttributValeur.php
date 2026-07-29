<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pim_fiche_attribute_value')]
#[ORM\Index(name: 'IDX_PIM_FAV_VALUE_FICHE', columns: ['value_id', 'fiche_id'])]
class FicheAttributValeur
{
    public function __construct(
        // value_id is globally unique across all LOV attributes, so fiche_id +
        // value_id safely identifies a link without attribute_code in the key.
        #[ORM\Id]
        #[ORM\ManyToOne(inversedBy: 'attributValues')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private Fiche $fiche,
        #[ORM\Column(length: 64)]
        private string $attributeCode,
        #[ORM\Id]
        #[ORM\Column(name: 'value_id', options: ['unsigned' => true])]
        private int $valueId,
    ) {
    }

    public function fiche(): Fiche { return $this->fiche; }
    public function attributeCode(): string { return $this->attributeCode; }
    public function valueId(): int { return $this->valueId; }
}
