<?php

namespace App\Pim\Service\Client;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @template T of object
 */
interface BatchClientInterface
{
    /**
     * @param array<string, ResponseInterface> $responses
     *
     * @return array<string, T[]>
     */
    public function resolve(array $responses): array;
}
