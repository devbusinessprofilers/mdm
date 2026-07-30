<?php

namespace App\Pim\Enum\ProviderPortal;

use Symfony\Contracts\Translation\TranslatableInterface;

interface TranslatableEnumInterface extends TranslatableInterface
{
    public function getTranslationKey(): string;
}
