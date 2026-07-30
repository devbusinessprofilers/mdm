<?php

namespace App\Pim\Model\ProviderPortal\Mock\Chart\Analytics;

class Performance
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

    public array $valueTurnoverBreakdownChartData = [];

    public array $valueTurnoverBreakdownChartOptions = [];

    public array $volumeTurnoverBreakdownChartData = [];

    public array $volumeTurnoverBreakdownChartOptions = [];

    public ?string $averageProcessTime = null;

    public ?string $averageProcessTimeVariation = null;

    public ?string $averageProcessTimeCritical = null;

    public ?string $conversionRate = null;

    public ?string $conversionRateVariation = null;

    public ?string $conversionRateCritical = null;

    public array $availabilityRequestProcessGaugeRanges = [];

    public array $quoteRequestProcessChartData = [];

    public array $quoteRequestProcessChartOptions = [];

    public array $refusalRequestProcessChartData = [];

    public array $refusalRequestProcessChartOptions = [];

    public ?int $refusalCount = null;

    public array $residenceRequestProfileChartData = [];

    public array $residenceRequestProfileChartOptions = [];

    public array $bookingRequestProfileChartData = [];

    public array $bookingRequestProfileChartOptions = [];

    public array $eventRequestProfileChartData = [];

    public array $eventRequestProfileChartOptions = [];

    public array $serviceRequestProfileChartData = [];

    public array $serviceRequestProfileChartOptions = [];

    public array $geographicalTopRequests = [];

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

        $data->valueTurnoverBreakdownChartData = [
            'labels' => ['Confirmé', 'En cours', 'Refus presta', 'Refus client', 'Perdu', 'Annulé'],
            'datasets' => [
                [
                    'data' => [15, 10, 25, 25, 10, 15],
                    'backgroundColor' => [
                        'rgb(50, 186, 85)',
                        'rgb(211, 179, 75)',
                        'rgb(51, 160, 190)',
                        'rgb(255, 201, 71)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 152, 148)',
                    ],
                ],
            ],
        ];

        $data->valueTurnoverBreakdownChartOptions = [
            'plugins' => [
                'datalabels' => [
                    'display' => true,
                    'color' => '#000000',
                    'font' => ['weight' => 'bold'],
                    'suffix' => '€',
                ],
            ],
        ];

        $data->volumeTurnoverBreakdownChartData = [
            'labels' => ['Confirmé', 'En cours', 'Refus presta', 'Refus client', 'Perdu', 'Annulé'],
            'datasets' => [
                [
                    'data' => [15, 10, 25, 25, 10, 15],
                    'backgroundColor' => [
                        'rgb(50, 186, 85)',
                        'rgb(211, 179, 75)',
                        'rgb(51, 160, 190)',
                        'rgb(255, 201, 71)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 152, 148)',
                    ],
                ],
            ],
        ];

        $data->volumeTurnoverBreakdownChartOptions = [
            'plugins' => [
                'datalabels' => [
                    'display' => true,
                    'color' => '#000000',
                    'font' => ['weight' => 'bold'],
                    'suffix' => '€',
                ],
            ],
        ];

        $data->averageProcessTime = '2h';
        $data->averageProcessTimeVariation = '0,1%';
        $data->averageProcessTimeCritical = true;
        $data->conversionRate = '18%';
        $data->conversionRateVariation = '0.1%';
        $data->conversionRateCritical = true;

        $data->availabilityRequestProcessGaugeRanges = [
            [
                'start' => 0,
                'end' => 4,
                'color' => 'rgb(51, 160, 190)',
                'label' => 'Lorem ipsum',
            ],
            [
                'start' => 4,
                'end' => 9,
                'color' => 'rgb(0, 255, 211)',
                'label' => 'Lorem ipsum',
            ],
            [
                'start' => 9,
                'end' => 40,
                'color' => 'rgb(255, 152, 148)',
                'label' => 'Lorem ipsum',
            ],
        ];

        $data->quoteRequestProcessChartData = [
            'labels' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet'],
            'datasets' => [
                [
                    'data' => [7, 3, 7, 0, 4, 3, 8],
                    'borderColor' => 'rgb(255, 152, 148)',
                ],
            ],
        ];

        $data->quoteRequestProcessChartOptions = [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'min' => 0,
                    'max' => 8,
                    'ticks' => [
                        'stepSize' => 1,
                        'autoSkip' => false,
                        'suffix' => 'h',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];

        $data->refusalRequestProcessChartData = [
            'labels' => ['Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit'],
            'datasets' => [
                [
                    'data' => [30, 20, 10, 30],
                    'backgroundColor' => [
                        'rgb(51, 160, 190)',
                        'rgb(255, 152, 148)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 201, 71)',
                    ],
                ],
            ],
        ];
        $data->refusalRequestProcessChartOptions = [];

        $data->refusalCount = 23;

        $data->residenceRequestProfileChartData = [
            'labels' => ['Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit'],
            'datasets' => [
                [
                    'data' => [15, 10, 25, 25, 10, 15],
                    'backgroundColor' => [
                        'rgb(50, 186, 85)',
                        'rgb(211, 179, 75)',
                        'rgb(51, 160, 190)',
                        'rgb(255, 201, 71)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 152, 148)',
                    ],
                ],
            ],
        ];

        $data->residenceRequestProfileChartOptions = [];

        $data->bookingRequestProfileChartData = [
            'labels' => ['Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit'],
            'datasets' => [
                [
                    'data' => [15, 10, 25, 25, 10, 15],
                    'backgroundColor' => [
                        'rgb(50, 186, 85)',
                        'rgb(211, 179, 75)',
                        'rgb(51, 160, 190)',
                        'rgb(255, 201, 71)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 152, 148)',
                    ],
                ],
            ],
        ];

        $data->bookingRequestProfileChartOptions = [];

        $data->eventRequestProfileChartData = [
            'labels' => ['Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit'],
            'datasets' => [
                [
                    'data' => [30, 20, 10, 30],
                    'backgroundColor' => [
                        'rgb(51, 160, 190)',
                        'rgb(255, 152, 148)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 201, 71)',
                    ],
                ],
            ],
        ];

        $data->eventRequestProfileChartOptions = [];

        $data->serviceRequestProfileChartData = [
            'labels' => ['Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit', 'Lorem ipsum dolor sit'],
            'datasets' => [
                [
                    'data' => [30, 20, 10, 30],
                    'backgroundColor' => [
                        'rgb(51, 160, 190)',
                        'rgb(255, 152, 148)',
                        'rgb(0, 255, 211)',
                        'rgb(255, 201, 71)',
                    ],
                ],
            ],
        ];

        $data->serviceRequestProfileChartOptions = [];

        $data->geographicalTopRequests = [
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
            ['department' => 'Région Parisienne, France', 'count' => 100],
        ];

        return $data;
    }
}
