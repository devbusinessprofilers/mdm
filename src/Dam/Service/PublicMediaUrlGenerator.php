<?php

declare(strict_types=1);

namespace App\Dam\Service;

final readonly class PublicMediaUrlGenerator
{
    public function __construct(private string $baseUrl)
    {
    }

    public function url(string $key): string
    {
        return rtrim($this->baseUrl, '/').'/'.implode('/', array_map(rawurlencode(...), explode('/', ltrim($key, '/'))));
    }
}
