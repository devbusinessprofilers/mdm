<?php

namespace App\Pim\Enum\ProviderPortal;

use Symfony\Contracts\Translation\TranslatorInterface;

enum SheetTypeEnum: string implements TranslatableEnumInterface
{
    case PLACE = 'place';
    case RESTAURANT = 'restaurant';
    case ACTIVITY = 'activity';
    case SERVICE = 'service';
    case MEAL_TRAY = 'meal_tray';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getTranslationKey());
    }

    public function getTranslationKey(): string
    {
        return 'global.enum.sheet_type.'.$this->name;
    }
}
