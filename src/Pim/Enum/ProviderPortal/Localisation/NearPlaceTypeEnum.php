<?php

namespace App\Pim\Enum\ProviderPortal\Localisation;

enum NearPlaceTypeEnum: string
{
    case TRAIN_STATION = 'train-station';
    case SUBWAY_STATION = 'subway-station';
    case LIGHT_RAIL_STATION = 'light-rail-station';
    case AIRPORT = 'airport';
    case CITY = 'city';
    case PARKING = 'parking';
    case POINT_OF_INTEREST = 'point-of-interest';
}
