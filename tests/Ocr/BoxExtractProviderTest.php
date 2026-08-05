<?php

declare(strict_types=1);

namespace App\Tests\Ocr;

use App\Ocr\Service\BoxExtractProvider;
use App\Ocr\Service\BoxProviderException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BoxExtractProviderTest extends TestCase
{
    public function testClientCredentialsUploadStructuredExtractionAndDelete(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"access_token":"token"}'),
            new MockResponse('{"entries":[{"id":"box-file"}]}'),
            new MockResponse('{"answer":{"fiche.label":{"value":"Nom","confidence_score":0.9}}}'),
            new MockResponse('', ['http_code' => 204]),
        ]);
        $provider = new BoxExtractProvider($client, 'client', 'secret', 'enterprise', 'subject', 'folder', 'https://api.box.test', 'https://upload.box.test/api', 'enhanced_extract_agent');
        $path = tempnam(sys_get_temp_dir(), 'ocr-box-test-');
        self::assertIsString($path);
        file_put_contents($path, '%PDF-test');
        try {
            self::assertSame('box-file', $provider->upload($path, 'batch.pdf'));
            self::assertSame('Nom', $provider->extract('box-file', [['key' => 'fiche.label', 'type' => 'string']])['answer']['fiche.label']['value']);
            $provider->delete('box-file');
        } finally { unlink($path); }
    }

    public function testRateLimitIsRetryableAndKeepsRetryAfter(): void
    {
        $provider = new BoxExtractProvider(new MockHttpClient([
            new MockResponse('{"access_token":"token"}'),
            new MockResponse('{"error":"rate_limit"}', ['http_code' => 429, 'response_headers' => ['Retry-After: 17']]),
        ]), 'client', 'secret', 'enterprise', 'subject', 'folder', 'https://api.box.test', 'https://upload.box.test/api', 'enhanced_extract_agent');
        try { $provider->extract('box-file', []); self::fail('Une erreur Box était attendue.'); }
        catch (BoxProviderException $error) { self::assertTrue($error->retryable); self::assertSame(17, $error->retryAfter); }
    }

    public function testUnauthorizedResponseRefreshesTokenOnce(): void
    {
        $requests = [];
        $provider = new BoxExtractProvider(new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $authorization = current(array_filter($options['headers'] ?? [], static fn (string $header): bool => str_starts_with(strtolower($header), 'authorization:'))) ?: null;
            $requests[] = [$method, $url, $authorization];
            if (str_ends_with($url, '/oauth2/token')) {
                $number = count(array_filter($requests, static fn (array $request): bool => str_ends_with($request[1], '/oauth2/token')));
                return new MockResponse(json_encode(['access_token' => 'token-'.$number], JSON_THROW_ON_ERROR));
            }
            if ('Authorization: Bearer token-1' === $authorization) { return new MockResponse('{}', ['http_code' => 401]); }
            return new MockResponse('{"answer":{}}');
        }), 'client', 'secret', 'enterprise', 'subject', 'folder', 'https://api.box.test', 'https://upload.box.test/api', 'enhanced_extract_agent');

        self::assertSame([], $provider->extract('box-file', [])['answer']);
        self::assertSame(['Authorization: Bearer token-1', 'Authorization: Bearer token-2'], array_values(array_filter(array_column($requests, 2))));
    }

    public function testServerErrorsAreRetryableAndInvalidJsonIsPermanent(): void
    {
        $serverError = new BoxExtractProvider(new MockHttpClient([
            new MockResponse('{"access_token":"token"}'),
            new MockResponse('{"error":"unavailable"}', ['http_code' => 503]),
        ]), 'client', 'secret', 'enterprise', 'subject', 'folder', 'https://api.box.test', 'https://upload.box.test/api', 'enhanced_extract_agent');
        try { $serverError->extract('box-file', []); self::fail('Une erreur Box était attendue.'); }
        catch (BoxProviderException $error) { self::assertTrue($error->retryable); }

        $invalidJson = new BoxExtractProvider(new MockHttpClient([
            new MockResponse('{"access_token":"token"}'),
            new MockResponse('not-json'),
        ]), 'client', 'secret', 'enterprise', 'subject', 'folder', 'https://api.box.test', 'https://upload.box.test/api', 'enhanced_extract_agent');
        try { $invalidJson->extract('box-file', []); self::fail('Une erreur Box était attendue.'); }
        catch (BoxProviderException $error) { self::assertFalse($error->retryable); }
    }
}
