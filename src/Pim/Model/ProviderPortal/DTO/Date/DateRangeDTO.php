<?php

namespace App\Pim\Model\ProviderPortal\DTO\Date;

class DateRangeDTO
{
    public ?\DateTime $from = null;

    public ?\DateTime $to = null;

    public function __construct(?\DateTime $from = null, ?\DateTime $to = null)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function display(string $format = 'd/m/Y'): string
    {
        return sprintf('%s - %s', $this->from?->format($format), $this->to?->format($format));
    }

    public static function mock(): self
    {
        $data = new self();

        $data->from = new \DateTime('+2 days');
        $data->to = new \DateTime('+10 days');

        return $data;
    }
}
