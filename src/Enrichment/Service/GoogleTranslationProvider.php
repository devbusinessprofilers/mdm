<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Enum\SupportedLocale;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleTranslationProvider implements TranslationProviderInterface
{
    // L'API v2 plafonne à 128 segments et ~30k caractères par requête :
    // marges de sécurité pour ne jamais faire échouer une fiche riche.
    private const MAX_SEGMENTS_PER_REQUEST = 100;
    private const MAX_CHARS_PER_REQUEST = 25000;

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

        $translated = [];
        foreach ($this->chunk($texts) as $chunk) {
            $translated = array_merge($translated, $this->request($chunk, $target));
        }

        return $translated;
    }

    /**
     * Découpe les sources en lots compatibles avec les limites de l'API, en
     * préservant l'ordre (les traductions sont réassemblées par concaténation).
     *
     * @param non-empty-list<string> $texts
     *
     * @return list<non-empty-list<string>>
     */
    private function chunk(array $texts): array
    {
        $chunks = [];
        $current = [];
        $length = 0;
        foreach ($texts as $text) {
            if ([] !== $current && (count($current) >= self::MAX_SEGMENTS_PER_REQUEST || $length + mb_strlen($text) > self::MAX_CHARS_PER_REQUEST)) {
                $chunks[] = $current;
                $current = [];
                $length = 0;
            }
            $current[] = $text;
            $length += mb_strlen($text);
        }
        $chunks[] = $current;

        return $chunks;
    }

    /**
     * @param non-empty-list<string> $texts
     *
     * @return list<string>
     */
    private function request(array $texts, SupportedLocale $target): array
    {
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
