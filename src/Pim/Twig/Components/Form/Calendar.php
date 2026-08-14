<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent]
class Calendar
{
    public string $id;

    public string $name;

    public ?string $value = null;

    /**
     * ICU date format used to display date (e.g. 'dd/MM/yyyy') => used to resolve ISO date expected by vanilla-calendar-pro.
     *
     * @see getIsoValue()
     */
    public string $inputFormat;

    public bool $disabled = false;

    public array $inputAttributes = [];

    public ?string $placeholder = null;

    public ?\DateTime $dateMin = null;

    public ?\DateTime $dateMax = null;

    /**
     * Return the current date (i.e. value) with format expected by vanilla-calendar-pro (ISO date Y-m-d).
     */
    #[ExposeInTemplate]
    public function getIsoValue(): ?string
    {
        if (empty($this->value)) {
            return null;
        }

        $transformer = new DateTimeToLocalizedStringTransformer(
            dateFormat: \IntlDateFormatter::NONE,
            pattern: $this->inputFormat,
        );

        return $transformer->reverseTransform($this->value)?->format('Y-m-d');
    }
}
