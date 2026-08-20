<?php

declare(strict_types=1);

namespace App\Tests\Vision;

use App\Vision\Service\OpenAiImageProvider;
use App\Vision\Service\OpenAiProviderException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiImageProviderTest extends TestCase
{
    public function testEnhanceSendsMultipartEditAndDecodesImageWithoutKeepingBase64(): void
    {
        $captured = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, $options];

            return new MockResponse(json_encode([
                'created' => 1,
                'usage' => ['total_tokens' => 42],
                'data' => [['b64_json' => base64_encode('binaire-png')]],
            ], JSON_THROW_ON_ERROR));
        });
        $provider = new OpenAiImageProvider($client, 'sk-test', 'https://api.openai.test');
        $path = tempnam(sys_get_temp_dir(), 'vision-test-');
        self::assertIsString($path);
        file_put_contents($path, 'fausse-image');
        try {
            $result = $provider->enhance($path, 'image/jpeg', 'Améliore la photo.', 'gpt-image-1');
        } finally {
            unlink($path);
        }

        self::assertSame('binaire-png', $result->bytes);
        self::assertSame('image/png', $result->mimeType);
        self::assertSame(42, $result->raw['usage']['total_tokens']);
        self::assertArrayNotHasKey('b64_json', $result->raw['data'][0]);
        self::assertSame(['POST', 'https://api.openai.test/v1/images/edits'], [$captured[0], $captured[1]]);
        $headers = array_map('strtolower', $captured[2]['headers'] ?? []);
        self::assertNotEmpty(array_filter($headers, static fn (string $header): bool => str_starts_with($header, 'authorization: bearer sk-test')));
        self::assertNotEmpty(array_filter($headers, static fn (string $header): bool => str_starts_with($header, 'content-type: multipart/form-data')));
    }

    public function testDescribeConstrainsJsonSchemaAndParsesContent(): void
    {
        $captured = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse(json_encode([
                'model' => 'gpt-4o',
                'usage' => ['total_tokens' => 7],
                'choices' => [['message' => ['content' => json_encode([
                    'legende' => ' Salle de réception lumineuse avec vue sur le parc. ',
                    'mots_cles' => ['réception', ' parquet ', '', 42],
                    'type_de_vue' => 'salle de réception',
                    'interieur_exterieur' => 'intérieur',
                ], JSON_THROW_ON_ERROR)]]],
            ], JSON_THROW_ON_ERROR));
        });
        $provider = new OpenAiImageProvider($client, 'sk-test', 'https://api.openai.test');
        $result = $provider->describe('https://cdn.test/photos/large/x.webp', 'Décris cette photo.', 'gpt-4o');

        self::assertSame('Salle de réception lumineuse avec vue sur le parc.', $result->legende);
        self::assertSame(['réception', 'parquet'], $result->keywords);
        self::assertSame(['vue_type' => 'salle de réception', 'interieur_exterieur' => 'intérieur'], $result->extras);
        self::assertArrayNotHasKey('choices', $result->raw);
        self::assertSame('https://api.openai.test/v1/chat/completions', $captured[1]);
        $body = $captured[2];
        self::assertSame('json_schema', $body['response_format']['type']);
        self::assertTrue($body['response_format']['json_schema']['strict']);
        self::assertSame('https://cdn.test/photos/large/x.webp', $body['messages'][0]['content'][1]['image_url']['url']);
    }

    public function testRateLimitAndServerErrorsAreRetryableInvalidJsonIsPermanent(): void
    {
        $rateLimited = new OpenAiImageProvider(new MockHttpClient([
            new MockResponse('{"error":"rate_limit"}', ['http_code' => 429, 'response_headers' => ['Retry-After: 23']]),
        ]), 'sk-test', 'https://api.openai.test');
        try {
            $rateLimited->describe('https://cdn.test/x.webp', 'p', 'gpt-4o');
            self::fail('Une erreur OpenAI était attendue.');
        } catch (OpenAiProviderException $error) {
            self::assertTrue($error->retryable);
            self::assertSame(23, $error->retryAfter);
        }

        $serverError = new OpenAiImageProvider(new MockHttpClient([
            new MockResponse('{"error":"unavailable"}', ['http_code' => 503]),
        ]), 'sk-test', 'https://api.openai.test');
        try {
            $serverError->describe('https://cdn.test/x.webp', 'p', 'gpt-4o');
            self::fail('Une erreur OpenAI était attendue.');
        } catch (OpenAiProviderException $error) {
            self::assertTrue($error->retryable);
        }

        $invalidJson = new OpenAiImageProvider(new MockHttpClient([new MockResponse('not-json')]), 'sk-test', 'https://api.openai.test');
        try {
            $invalidJson->describe('https://cdn.test/x.webp', 'p', 'gpt-4o');
            self::fail('Une erreur OpenAI était attendue.');
        } catch (OpenAiProviderException $error) {
            self::assertFalse($error->retryable);
        }
    }

    public function testMissingApiKeyFailsBeforeAnyNetworkCall(): void
    {
        $provider = new OpenAiImageProvider(new MockHttpClient(static function (): MockResponse {
            self::fail('Aucun appel réseau ne doit partir sans clé API.');
        }), '', 'https://api.openai.test');
        $this->expectException(OpenAiProviderException::class);
        $provider->describe('https://cdn.test/x.webp', 'p', 'gpt-4o');
    }
}
