<?php

declare(strict_types=1);

namespace App\Vision\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client OpenAI des deux usages Vision : retouche d'image (images/edits) et
 * reconnaissance (chat completions vision à sortie JSON contrainte). Le modèle
 * et le prompt viennent des paramètres applicatifs et sont passés à l'appel.
 */
final class OpenAiImageProvider implements ImageEnhancementProviderInterface, ImageRecognitionProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $apiUrl,
    ) {
    }

    public function enhance(string $imagePath, string $mimeType, string $prompt, string $model): EnhancedImageResult
    {
        if (!is_file($imagePath)) {
            throw new \InvalidArgumentException('La copie locale de l’image à retoucher est introuvable.');
        }
        $form = new FormDataPart([
            'model' => $model,
            'prompt' => $prompt,
            'size' => 'auto',
            'input_fidelity' => 'high',
            'output_format' => 'png',
            'image' => DataPart::fromPath($imagePath, basename($imagePath), $mimeType),
        ]);
        $response = $this->request('POST', '/v1/images/edits', [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
        ]);
        $payload = $this->json($response, 'retouche');
        $encoded = $payload['data'][0]['b64_json'] ?? null;
        if (!is_string($encoded) || '' === $encoded) {
            throw new OpenAiProviderException('Réponse OpenAI de retouche sans image.', false);
        }
        $bytes = base64_decode($encoded, true);
        if (false === $bytes) {
            throw new OpenAiProviderException('Image OpenAI illisible (base64 invalide).', false);
        }
        unset($payload['data'][0]['b64_json']);

        return new EnhancedImageResult($bytes, 'image/png', $payload);
    }

    public function describe(string $imageUrl, string $prompt, string $model): RecognitionResult
    {
        $response = $this->request('POST', '/v1/chat/completions', ['json' => [
            'model' => $model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    // Détail bas : l'image est facturée 85 tokens au lieu de
                    // ~425 pour la rendition large — suffisant pour légende et
                    // mots-clés, à réévaluer si la qualité déçoit.
                    ['type' => 'image_url', 'image_url' => ['url' => $imageUrl, 'detail' => 'low']],
                ],
            ]],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'reconnaissance_image',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['legende', 'mots_cles', 'type_de_vue', 'interieur_exterieur'],
                        'properties' => [
                            'legende' => ['type' => 'string', 'description' => 'Légende en français, une phrase.'],
                            'mots_cles' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'type_de_vue' => ['type' => 'string', 'description' => 'Type de vue : salle, façade, terrasse…'],
                            'interieur_exterieur' => ['type' => 'string', 'enum' => ['intérieur', 'extérieur', 'indéterminé']],
                        ],
                    ],
                ],
            ],
        ]]);
        $payload = $this->json($response, 'reconnaissance');
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || '' === $content) {
            throw new OpenAiProviderException('Réponse OpenAI de reconnaissance sans contenu.', false);
        }
        try {
            $parsed = json_decode($content, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new OpenAiProviderException('Contenu OpenAI de reconnaissance illisible (JSON invalide).', false, null, $e);
        }
        if (!is_array($parsed) || !is_string($parsed['legende'] ?? null)) {
            throw new OpenAiProviderException('Contenu OpenAI de reconnaissance incomplet.', false);
        }
        $keywords = [];
        foreach (is_array($parsed['mots_cles'] ?? null) ? $parsed['mots_cles'] : [] as $keyword) {
            if (is_string($keyword) && '' !== trim($keyword)) {
                $keywords[] = trim($keyword);
            }
        }
        $extras = [];
        foreach (['type_de_vue' => 'vue_type', 'interieur_exterieur' => 'interieur_exterieur'] as $source => $key) {
            if (is_string($parsed[$source] ?? null) && '' !== trim($parsed[$source])) {
                $extras[$key] = trim($parsed[$source]);
            }
        }
        unset($payload['choices']);

        return new RecognitionResult(trim($parsed['legende']), $keywords, $extras, $payload);
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
