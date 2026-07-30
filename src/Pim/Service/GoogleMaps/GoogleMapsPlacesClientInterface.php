<?php

namespace App\Pim\Service\GoogleMaps;

interface GoogleMapsPlacesClientInterface
{
    // https://developers.google.com/maps/documentation/places/web-service/place-types?hl=fr
    public const PLACE_TYPE_FULL_ADDRESS = 'street_address';
    public const PLACE_TYPE_COUNTRY = 'country';
    public const PLACE_TYPE_CITY = 'locality';
    public const PLACE_TYPE_ZIP_CODE = 'postal_code';
    public const PLACE_TYPE_NUMBER = 'street_number';
    public const PLACE_TYPE_STREET = 'route';
    public const PLACE_TYPE_AREA = 'administrative_area_level_1';
    public const PLACE_TYPE_DEPARTMENT = 'administrative_area_level_2';
    public const PLACE_TYPE_DISTRICT = 'administrative_area_level_3';
    public const PLACE_TYPE_TRAIN_STATION = 'train_station';
    public const PLACE_TYPE_SUBWAY_STATION = 'subway_station';
    public const PLACE_TYPE_LIGHT_RAIL_STATION = 'light_rail_station';
    public const PLACE_TYPE_AIRPORT = 'international_airport';
    public const PLACE_TYPE_PARKING = 'parking';
    public const PLACE_TYPE_POINT_OF_INTEREST = 'point_of_interest';
}
