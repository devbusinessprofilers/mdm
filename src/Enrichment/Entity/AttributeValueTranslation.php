<?php

declare(strict_types=1);

namespace App\Enrichment\Entity;

use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Pim\Entity\ValeurAttribut;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeValueTranslationRepository::class)]
#[ORM\Table(name: 'pim_attribute_value_translation')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_ATTRIBUTE_VALUE_LOCALE', columns: ['value_id', 'locale'])]
class AttributeValueTranslation extends AbstractLovTranslation
{
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'value_id', nullable: false, onDelete: 'CASCADE')]
        private ValeurAttribut $value,
        SupportedLocale $locale,
        string $source,
    ) { $this->initializeTranslation($locale, $source); }

    public function value(): ValeurAttribut { return $this->value; }
}
