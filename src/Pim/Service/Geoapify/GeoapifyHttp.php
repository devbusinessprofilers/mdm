<?php

declare(strict_types=1);

namespace App\Pim\Service\Geoapify;

use App\Shared\Http\ClientHttpLisse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transport vers l'API Geoapify : clé en query, lissage sous les 5 req/s du
 * plan gratuit, réessai unique sur 429, codes HTTP attendus, corps JSON
 * décodé. Les messages d'erreur ne contiennent jamais la clé.
 */
final class GeoapifyHttp
{
    /** Le plan gratuit est limité à 5 req/s : on lisse en dessous. */
    private const INTERVALLE_MIN_SECONDES = 0.25;

    private readonly ClientHttpLisse $transport;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $endpoint,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->transport = new ClientHttpLisse($httpClient->withOptions(['timeout' => 30, 'max_duration' => 120]), self::INTERVALLE_MIN_SECONDES);
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey) && '' !== trim($this->endpoint);
    }

    /**
     * @param array<string, string> $query
     * @param mixed                 $json     corps JSON éventuel (POST)
     * @param list<int>             $attendus codes HTTP acceptés ; 202 (job en cours) → null
     *
     * @return array<array-key, mixed>|null
     *
     * @throws \RuntimeException API injoignable ou code HTTP inattendu
     */
    public function requete(string $method, string $chemin, array $query, mixed $json = null, array $attendus = [200, 202]): ?array
    {
        $options = ['query' => $query + ['apiKey' => $this->apiKey]];
        if (null !== $json) {
            $options['json'] = $json;
        }
        try {
            $response = $this->transport->request($method, rtrim($this->endpoint, '/').$chemin, $options);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            // Pas de chaînage de l'exception d'origine : son message contient
            // l'URL appelée, clé API incluse.
            throw new \RuntimeException('Geoapify est injoignable : '.$this->sansCle($exception->getMessage()));
        }
        if (!in_array($status, $attendus, true)) {
            throw new \RuntimeException(sprintf('Geoapify a répondu HTTP %d.', $status));
        }
        if (202 === $status && 'GET' === $method) {
            return null;
        }
        $donnees = json_decode($body, true);

        return is_array($donnees) ? $donnees : null;
    }

    private function sansCle(string $message): string
    {
        return '' === $this->apiKey ? $message : str_replace($this->apiKey, '***', $message);
    }
}
