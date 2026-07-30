<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Suggestion
{
    public string $id;

    public string $value;

    public string $name;

    public bool $multiple;

    public bool $disabled = false;

    public bool $withIndicator = true;

    public array $inputAttributes = [];

    public string $stimulusDataActions = '';

    public ?string $placeholder = null;

    public ?string $tip = null;

    public ?string $tipIcon = null;

    public ?string $wrapperClass = null;

    public function mount(?bool $multiple = false, ?array $stimulusDataActions = null): void
    {
        $this->multiple = $multiple;

        if ($stimulusDataActions) {
            foreach ($stimulusDataActions as $action) {
                $this->stimulusDataActions .= \sprintf(' %s', $action);
            }
        }

        $this->stimulusDataActions = \trim($this->stimulusDataActions);
    }
}
