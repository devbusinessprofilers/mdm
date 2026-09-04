<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Transport HTTP vers une API externe soumise à un quota par seconde :
 * espace les requêtes d'un intervalle minimal et, sur un 429 malgré le
 * lissage, réessaie une fois après le délai Retry-After (borné). Les clients
 * Geoapify et Recherche d'entreprises recopiaient cette mécanique.
 */
final class ClientHttpLisse
{
    private float $derniereRequete = 0.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly float $intervalleMinSecondes,
        private readonly int $attenteMaxSecondes = 30,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = $this->executer($method, $url, $options);
        if (429 === $response->getStatusCode()) {
            sleep($this->retryAfter($response));
            $response = $this->executer($method, $url, $options);
        }

        return $response;
    }

    /** @param array<string, mixed> $options */
    private function executer(string $method, string $url, array $options): ResponseInterface
    {
        $ecoule = microtime(true) - $this->derniereRequete;
        if ($ecoule < $this->intervalleMinSecondes) {
            usleep((int) (($this->intervalleMinSecondes - $ecoule) * 1_000_000));
        }
        $this->derniereRequete = microtime(true);

        return $this->httpClient->request($method, $url, $options);
    }

    private function retryAfter(ResponseInterface $response): int
    {
        $valeur = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);

        return max(1, min($this->attenteMaxSecondes, $valeur));
    }
}
