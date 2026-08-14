<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart as ModelChart;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Chart
{
    public string $type;

    public ModelChart $chart;

    public function __construct(
        private readonly ChartBuilderInterface $builder
    ) {
    }

    public function mount(string $type, ?array $data = [], ?array $options = []): void
    {
        $this->chart = $this->builder->createChart($type)
            ->setData($data)
            ->setOptions($options)
        ;
    }
}
