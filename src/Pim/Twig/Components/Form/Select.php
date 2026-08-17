<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Select
{
    public string $id;

    public string $name;

    public bool $multiple = false;

    /**
     * @var array<ChoiceView>
     */
    public array $choices = [];

    /**
     * @var string|array<string>|null
     */
    public string|array|null $value = null;

    public bool $disabled = false;

    public bool $withIndicator = true;

    /** Un select simple non requis porte une option vide (« rien de choisi »). */
    public bool $required = false;

    public bool $prototype = false;

    public array $selectAttributes = [];

    public ?string $placeholder = null;

    public ?string $tip = null;

    public ?string $tipIcon = null;

    public ?string $wrapperClass = null;

    public ?int $limit = null;

    /**
     * Get selected value(s) as array.
     */
    public function getSelectedValues(): array
    {
        if (null === $this->value) {
            return [];
        }

        return is_array($this->value) ? $this->value : [$this->value];
    }
}
