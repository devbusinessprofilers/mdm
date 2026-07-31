<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class TagSelect
{
    public string $id;

    public string $name;

    /**
     * @var array<ChoiceView>
     */
    public array $choices = [];

    /**
     * @var array<string>
     */
    public array $value = [];

    public function isSelected(string $choice): bool
    {
        return in_array($choice, $this->value);
    }
}
