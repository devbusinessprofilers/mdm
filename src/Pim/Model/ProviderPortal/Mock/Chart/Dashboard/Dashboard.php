<?php

namespace App\Pim\Model\ProviderPortal\Mock\Chart\Dashboard;

class Dashboard
{
    public ?string $turnover = null;

    public ?string $turnoverVariation = null;

    public ?bool $turnoverCritical = null;

    public ?string $bookingCount = null;

    public ?string $bookingCountVariation = null;

    public ?bool $bookingCountCritical = null;

    public ?string $averageBasket = null;

    public ?string $averageBasketVariation = null;

    public ?bool $averageBasketCritical = null;

    public ?string $individualAverageBasket = null;

    public ?string $individualAverageBasketVariation = null;

    public ?bool $individualAverageBasketCritical = null;

    /**
     * NOTE: bar chart data with 2 entries.
     */
    public array $turnoverChartData1 = [];

    public array $turnoverChartOptions1 = [];

    /**
     * NOTE: line chart data with 1 entry.
     */
    public array $turnoverChartData2 = [];

    public array $turnoverChartOptions2 = [];

    public static function mock(): self
    {
        $data = new self();

        $data->turnover = '137 893 € HT';
        $data->turnoverVariation = '0,1%';
        $data->turnoverCritical = true;
        $data->bookingCount = '2';
        $data->bookingCountVariation = '0,1%';
        $data->bookingCountCritical = true;
        $data->averageBasket = '68 497 €';
        $data->averageBasketVariation = '0,1%';
        $data->averageBasketCritical = false;
        $data->individualAverageBasket = '944 €';
        $data->individualAverageBasketVariation = '0,1%';
        $data->individualAverageBasketCritical = true;

        $data->turnoverChartData1 = [
            'labels' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin'],
            'datasets' => [
                [
                    'label' => 'Lorem ipsum dolor sit amet',
                    'data' => [80, 80, 80, 80, 80, 80],
                    'backgroundColor' => 'rgb(255, 152, 148)',
                ],
                [
                    'label' => 'Lorem ipsum dolor sit amet',
                    'data' => [40, 40, 40, 40, 40, 40],
                    'backgroundColor' => 'rgb(51, 160, 190)',
                ],
            ],
        ];

        $data->turnoverChartOptions1 = [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'min' => 0,
                    'max' => 90,
                    'ticks' => [
                        'stepSize' => 10,
                        'autoSkip' => false,
                        'suffix' => 'k',
                    ],
                ],
            ],
        ];

        $data->turnoverChartData2 = [
            'labels' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet'],
            'datasets' => [
                [
                    'label' => 'Lorem ipsum dolor sit amet',
                    'data' => [70, 30, 70, 0, 40, 30, 70],
                    'borderColor' => 'rgb(255, 152, 148)',
                ],
            ],
        ];

        $data->turnoverChartOptions2 = [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'min' => 0,
                    'max' => 70,
                    'ticks' => [
                        'stepSize' => 10,
                        'autoSkip' => false,
                        'suffix' => 'k',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];

        return $data;
    }
}
