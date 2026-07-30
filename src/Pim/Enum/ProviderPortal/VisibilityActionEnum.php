<?php

namespace App\Pim\Enum\ProviderPortal;

use Symfony\Contracts\Translation\TranslatorInterface;

enum VisibilityActionEnum: string implements TranslatableEnumInterface
{
    case LINKEDIN_PUBLICATION = 'linkedin_publication';
    case MARKETING_RESEARCH = 'marketing_research';
    case ONE_TARGETED_EMAIL = 'one_targeted_email';
    case TWO_TARGETED_EMAIL = 'two_targeted_email';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getTranslationKey());
    }

    public function getTranslationKey(): string
    {
        return 'global.enum.visibility_action.'.$this->name;
    }
}
