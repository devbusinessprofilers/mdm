<?php

declare(strict_types=1);

namespace App\Dam\Twig;

use App\Dam\Service\DamAnomalyCounter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DamExtension extends AbstractExtension
{
    public function __construct(private readonly DamAnomalyCounter $counter)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('dam_anomaly_counts', $this->counter->forFiche(...)),
        ];
    }
}
