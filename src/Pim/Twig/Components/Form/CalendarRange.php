<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent]
class CalendarRange
{
    public string $id;

    public string $name;

    public ?string $value = null;

    public string $separator;

    /**
     * ICU date format used to display date (e.g. 'dd/MM/yyyy') => used to resolve ISO date expected by vanilla-calendar-pro.
     *
     * @see getInitialRange()
     */
    public string $inputFormat;

    public bool $disabled = false;

    public array $inputAttributes = [];

    public ?string $placeholder = null;

    public ?\DateTime $dateMin = null;

    public ?\DateTime $dateMax = null;

    private DateTimeToLocalizedStringTransformer $transformer;

    /**
     * Return the current range (i.e. value) with format expected by vanilla-calendar-pro (ISO date Y-m-d).
     */
    #[ExposeInTemplate]
    public function getInitialRange(): array
    {
        if (empty($this->value)) {
            return [];
        }

        $data = explode(sprintf('%s', $this->separator), $this->value);

        $from = \DateTime::createFromFormat($this->inputFormat, $data[0] ?? '');
        $to = \DateTime::createFromFormat($this->inputFormat, $data[1] ?? '');

        if (false === $from || false === $to) {
            return [];
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }
}
