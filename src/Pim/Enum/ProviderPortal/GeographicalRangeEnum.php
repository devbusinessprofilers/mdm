<?php

namespace App\Pim\Enum\ProviderPortal;

use Symfony\Contracts\Translation\TranslatorInterface;

enum GeographicalRangeEnum: string implements TranslatableEnumInterface
{
    case FIX_ADDRESS = 'fix_address';
    case MOBILE = 'mobile';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getTranslationKey());
    }

    public function getTranslationKey(): string
    {
        return 'global.enum.geographical_range.'.$this->name;
    }
}
