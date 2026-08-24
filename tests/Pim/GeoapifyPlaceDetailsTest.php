<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\GeoapifyClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeoapifyPlaceDetailsTest extends TestCase
{
    public function testExtraitLesAttributsOsmDuPlaceDetails(): void
    {
        $client = self::client(['features' => [[
            'properties' => ['datasource' => ['raw' => [
                'cuisine' => 'italian;pizza',
                'diet:vegetarian' => 'yes',
                'diet:vegan' => 'no',
                'wheelchair' => 'yes',
                'toilets:wheelchair' => 'no',
                'outdoor_seating' => 'yes',
                'internet_access' => 'wlan',
                'website' => 'https://trattoria.example',
            ]]],
        ]]]);

        $attributs = $client->detailsPlace('48.8', '2.3');

        self::assertNotNull($attributs);
        self::assertSame(['italian', 'pizza'], $attributs->cuisines);
        self::assertSame(['vegetarian'], $attributs->regimes);
        self::assertTrue($attributs->accesPmr);
        self::assertFalse($attributs->toilettesPmr);
        self::assertTrue($attributs->terrasse);
        self::assertTrue($attributs->wifi);
        self::assertNull($attributs->climatisation);
        self::assertSame('https://trattoria.example', $attributs->siteWeb);
    }

    public function testRetourneNullSansFeature(): void
    {
        self::assertNull(self::client(['features' => []])->detailsPlace('48.8', '2.3'));
    }

    public function testDesactiveSansCle(): void
    {
        $client = new GeoapifyClient(new MockHttpClient(), 'https://geoapify.example', '');
        self::assertNull($client->detailsPlace('48.8', '2.3'));
    }

    /** @param array<string, mixed> $payload */
    private static function client(array $payload): GeoapifyClient
    {
        return new GeoapifyClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR))),
            'https://geoapify.example',
            'test-key',
        );
    }
}
