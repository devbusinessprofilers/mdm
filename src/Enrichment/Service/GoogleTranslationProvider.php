<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Enum\SupportedLocale;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleTranslationProvider implements TranslationProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $endpoint = 'https://translation.googleapis.com/language/translate/v2',
    ) {
    }

    public function translate(array $texts, SupportedLocale $target): array
    {
        if ([] === $texts) {
            return [];
        }
        if ('' === trim($this->apiKey)) {
            throw new \RuntimeException('GOOGLE_TRANSLATE_API_KEY is not configured.');
        }

        $response = $this->httpClient->request('POST', $this->endpoint, [
            'query' => ['key' => $this->apiKey],
            'json' => [
                'q' => $texts,
                'source' => SupportedLocale::Fr->value,
                'target' => $target->value,
                'format' => 'text',
            ],
            'timeout' => 30,
        ]);
        /** @var array{data?: array{translations?: list<array{translatedText?: string}>}} $payload */
        $payload = $response->toArray();
        $translations = $payload['data']['translations'] ?? null;
        if (!is_array($translations) || count($translations) !== count($texts)) {
            throw new \UnexpectedValueException('Invalid Google Translation response.');
        }

        return array_map(
            static function (array $translation): string {
                if (!isset($translation['translatedText'])) {
                    throw new \UnexpectedValueException('Missing translatedText in Google response.');
                }

                return html_entity_decode(
                    trim($translation['translatedText']),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                );
            },
            $translations,
        );
    }
}
