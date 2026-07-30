<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class MoneyInput
{
    public string $id;

    public string $name;

    public string $currencyIcon = 'currency-euro';

    public float $step = 0.01;

    public int $scale = 0;

    public float $minValue = 0;

    public ?float $maxValue = null;

    public string $radix = ',';

    public string $thousandsSeparator = ' ';

    public bool $padFractionalZeros = true;

    public bool $disabled = false;

    public bool $readonly = false;

    /**
     * Allows to add attributes key/value to input field
     * e.g. ['data-foo' => 'bar'] will render 'data-foo="bar"' on input.
     *
     * @var array<string, string>
     */
    public array $inputAttributes = [];

    /**
     * Allows to add stimulus action on input field
     * e.g. ['click->foo-controller#onClick', 'change->foo-controller#onChange'] will render
     * data-action="click->foo-controller#onClick change->foo-controller#onChange" on input.
     *
     * @var array<string>
     */
    public array $stimulusDataActions = [];
}
