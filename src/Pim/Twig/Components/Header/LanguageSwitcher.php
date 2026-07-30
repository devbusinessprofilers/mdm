<?php

namespace App\Pim\Twig\Components\Header;

use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LanguageSwitcher
{
    public string $currentLocale;

    public array $availableLocales = ['fr', 'en'];

    public function __construct(
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
        $this->currentLocale = $this->localeSwitcher->getLocale();
    }
}
