<?php

namespace App\Pim\Service\Map;

use Symfony\UX\Map\Icon\Icon;

class MapPinFactory
{
    private const ICONS_PREFIX = 'provider-portal-pin';

    public static function createHomePin(): Icon
    {
        return self::createPin(\sprintf('%s:%s', self::ICONS_PREFIX, 'bg-home-pin'));
    }

    public static function createDefaultPin(): Icon
    {
        return self::createPin(\sprintf('%s:%s', self::ICONS_PREFIX, 'bg-pin'));
    }

    public static function createPin(string $iconName): Icon
    {
        return Icon::ux($iconName)
            ->width(48)
            ->height(48)
        ;
    }
}
