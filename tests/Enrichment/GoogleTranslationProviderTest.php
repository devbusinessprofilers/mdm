<?php

declare(strict_types=1);

namespace App\Tests\Enrichment;

use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Service\GoogleTranslationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleTranslationProviderTest extends TestCase
{
    public function testItCallsGoogleV2AndDecodesTheTranslatedTexts(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://translation.test/v2?key=secret', $url);
            self::assertSame([
                'q' => ['Salle & terrasse', 'Accès PMR'],
                'source' => 'fr',
                'target' => 'en',
                'format' => 'text',
            ], json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR));

            return new MockResponse(json_encode([
                'data' => ['translations' => [
                    ['translatedText' => 'Room &amp; terrace'],
                    ['translatedText' => 'Disabled access'],
                ]],
            ], JSON_THROW_ON_ERROR));
        });
        $provider = new GoogleTranslationProvider($client, 'secret', 'https://translation.test/v2');

        self::assertSame(
            ['Room & terrace', 'Disabled access'],
            $provider->translate(['Salle & terrasse', 'Accès PMR'], SupportedLocale::En),
        );
    }

    public function testItRefusesARequestWithoutAnApiKey(): void
    {
        $provider = new GoogleTranslationProvider(new MockHttpClient(), '');

        $this->expectException(\RuntimeException::class);
        $provider->translate(['Bonjour'], SupportedLocale::En);
    }
}
