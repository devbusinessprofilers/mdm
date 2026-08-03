<?php

declare(strict_types=1);

namespace App\Dam\Service;

final class ImageVariantRegistry
{
    /** @return array<string, array{width: int, height: int}> */
    public static function all(): array
    {
        return [
            'hd' => ['width' => 1920, 'height' => 1080],
            'large' => ['width' => 960, 'height' => 480],
            'medium_2' => ['width' => 320, 'height' => 190],
            'medium' => ['width' => 300, 'height' => 150],
            'small' => ['width' => 200, 'height' => 100],
            'map' => ['width' => 194, 'height' => 150],
            'cart' => ['width' => 80, 'height' => 70],
        ];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
