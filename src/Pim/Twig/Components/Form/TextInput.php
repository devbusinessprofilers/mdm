<?php

namespace App\Pim\Twig\Components\Form;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class TextInput
{
    public string $id;

    public string $name;

    public ?string $value = null;

    public TypographyTextColorEnum $iconColor;

    public bool $disabled = false;

    public bool $readonly = false;

    public array $inputAttributes = [];

    public ?string $placeholder = null;

    public ?string $leftIcon = null;

    public ?string $rightIcon = null;

    public function mount(?string $rightIcon = null, ?string $iconColor = null): void
    {
        $this->rightIcon = $this->disabled ? 'lock' : $rightIcon;
        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->iconColor = $this->disabled ? TypographyTextColorEnum::CONTROLLED : ((null !== $iconColor ? TypographyTextColorEnum::tryFrom($iconColor) : null) ?? TypographyTextColorEnum::DARK);
    }
}
