<?php

declare(strict_types=1);

namespace App\Vision\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client OpenAI pour la suggestion de texte (chat completions). Même
 * authentification et même mapping d'erreurs que {@see OpenAiImageProvider} ;
 * le modèle vient des paramètres applicatifs et est passé à l'appel.
 */
final class OpenAiTextProvider implements TextSuggestionProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $apiUrl,
    ) {
    }

    public function suggerer(string $prompt, string $model): string
    {
        $response = $this->request('POST', '/v1/chat/completions', ['json' => [
            'model' => $model,
            'messages' => [[
                'role' => 'user',
                'content' => $prompt,
            ]],
        ]]);
        $payload = $this->json($response, 'suggestion');
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || '' === trim($content)) {
            throw new OpenAiProviderException('Réponse OpenAI de suggestion sans contenu.', false);
        }

        return trim($content);
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $path, array $options): ResponseInterface
    {
        if ('' === trim($this->apiKey)) {
            throw new OpenAiProviderException('Clé API OpenAI absente (OPENAI_API_KEY).', false);
        }
        $options['auth_bearer'] = $this->apiKey;

        return $this->httpClient->request($method, rtrim($this->apiUrl, '/').$path, $options);
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response, string $operation): array
    {
        $status = $response->getStatusCode();
        if ($status >= 300) {
            $retryAfter = null;
            if (429 === $status) {
                $headers = $response->getHeaders(false);
                $retryAfter = isset($headers['retry-after'][0]) ? max(1, (int) $headers['retry-after'][0]) : 60;
            }
            throw new OpenAiProviderException(sprintf('OpenAI %s a échoué (HTTP %d).', $operation, $status), 429 === $status || $status >= 500, $retryAfter);
        }
        try {
            return $response->toArray(false);
        } catch (\Throwable $e) {
            throw new OpenAiProviderException('Réponse JSON OpenAI invalide pour '.$operation.'.', false, null, $e);
        }
    }
}
