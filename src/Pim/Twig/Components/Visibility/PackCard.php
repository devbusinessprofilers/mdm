<?php

namespace App\Pim\Twig\Components\Visibility;

use App\Pim\Enum\ProviderPortal\Twig\Component\Button\ButtonVariantEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Tag\TagVariantEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PackCard
{
    public string $label;
    public int $value;
    public bool $isPremium;
    public bool $isSelected;
    public TypographyTextColorEnum $labelColor;
    public ButtonVariantEnum $buttonVariant;
    public ?string $buttonIcon;
    public string $buttonLink;
    public ?TagVariantEnum $tagVariant;
    public ?string $tagLabel;

    /** @var string[] */
    public array $options = [];

    public function mount(int $value, ?string $action = null, ?bool $isSelected = false): void
    {
        $this->value = $value;
        $this->isSelected = $isSelected;
        $this->isPremium = $value > 0;
        $this->buttonLink = $action ?? '#';

        if ($this->isPremium) {
            $this->labelColor = TypographyTextColorEnum::OLD_GOLD;
        } else {
            $this->labelColor = TypographyTextColorEnum::PRIMARY;
        }

        if ($this->isSelected) {
            $this->buttonVariant = ButtonVariantEnum::PRIMARY;
            $this->buttonIcon = 'check';
        } else {
            $this->buttonVariant = ButtonVariantEnum::OUTLINE;
            $this->buttonIcon = null;
        }

        if ($this->isPremium && $this->isSelected) {
            $this->tagVariant = TagVariantEnum::OLD_GOLD;
            $this->tagLabel = 'visibility.pack.premium';
        } else {
            $this->tagVariant = null;
            $this->tagLabel = null;
        }
    }
}
