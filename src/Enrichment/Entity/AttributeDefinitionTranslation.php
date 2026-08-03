<?php

declare(strict_types=1);

namespace App\Enrichment\Entity;

use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\AttributDefinition;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pim_attribute_definition_translation')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_ATTRIBUTE_DEFINITION_LOCALE', columns: ['attribute_id', 'locale'])]
class AttributeDefinitionTranslation extends AbstractLovTranslation
{
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private AttributDefinition $attribute,
        SupportedLocale $locale,
        string $source,
    ) { $this->initializeTranslation($locale, $source); }

    public function attribute(): AttributDefinition { return $this->attribute; }
}
