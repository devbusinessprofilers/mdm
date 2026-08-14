<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Gauge
{
    /**
     * @var array<array{start: int, end: int, label: string, color: string}>
     */
    public array $ranges;

    public float $value = 0;

    public int $min;

    public int $max;

    public bool $showLabel = true;

    public function mount(array $ranges, ?int $min = null, ?int $max = null): void
    {
        if (empty($ranges)) {
            throw new \LogicException('at least one range must be provided');
        }

        [$this->min, $this->max] = $this->resolveMinMax($ranges);
    }

    private function resolveMinMax(array $ranges): array
    {
        $min = $max = null;

        foreach ($ranges as $range) {
            if (null === $min) {
                $min = $range['start'];
            }
            if (null === $max) {
                $max = $range['end'];
            }

            $min = min($min, $range['start']);
            $max = max($max, $range['end']);
        }

        return [$min, $max];
    }
}
