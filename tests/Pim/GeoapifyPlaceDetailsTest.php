<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\EnrichissementIndisponibleException;
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
                'contact:phone' => '+33 1 23 45 67 89',
                'brand' => 'Big Mamma',
                'operator' => 'Exploitant SARL',
                'stars' => '4S',
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
        self::assertSame('+33 1 23 45 67 89', $attributs->telephone);
        // `brand` seulement : `operator` est l'exploitant, pas l'enseigne.
        self::assertSame('Big Mamma', $attributs->marque);
        // « 4S » = 4 étoiles supérieur.
        self::assertSame(4, $attributs->etoiles);
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

    public function testNeRetientQueLesFeaturesDontLeNomCorrespond(): void
    {
        $client = self::client(['features' => [
            // Bâtiment sans nom : ses tags ne doivent pas être fusionnés.
            ['properties' => ['datasource' => ['raw' => ['wheelchair' => 'yes']]]],
            ['properties' => ['name' => 'Chez Marcel', 'datasource' => ['raw' => [
                'name' => 'Chez Marcel',
                'cuisine' => 'french',
                'website' => 'https://marcel.example',
            ]]]],
        ]]);

        $attributs = $client->detailsPlace('48.8', '2.3', 'Chez Marcel');

        self::assertNotNull($attributs);
        self::assertSame(['french'], $attributs->cuisines);
        self::assertSame('https://marcel.example', $attributs->siteWeb);
        self::assertNull($attributs->accesPmr);
    }

    public function testAucunResultatQuandAucunNomNeCorrespond(): void
    {
        $client = self::client(['features' => [
            ['properties' => ['name' => 'Le Voisin', 'datasource' => ['raw' => ['name' => 'Le Voisin', 'cuisine' => 'thai']]]],
        ]]);

        self::assertNull($client->detailsPlace('48.8', '2.3', 'La Bella Trattoria'));
    }

    public function testLaCorrespondanceDeNomIgnoreLesAccents(): void
    {
        $client = self::client(['features' => [
            ['properties' => ['name' => 'Hôtel de la Gare', 'datasource' => ['raw' => ['name' => 'Hôtel de la Gare', 'cuisine' => 'french']]]],
        ]]);

        self::assertNotNull($client->detailsPlace('48.8', '2.3', 'Hotel de la Gare'));
    }

    public function testIndisponibiliteLeveeSansExposerLaCle(): void
    {
        $client = new GeoapifyClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse('', [
                'error' => 'boom https://geoapify.example/v2/place-details?apiKey=test-key',
            ])),
            'https://geoapify.example',
            'test-key',
        );

        try {
            $client->detailsPlace('48.8', '2.3');
            self::fail('Une EnrichissementIndisponibleException était attendue.');
        } catch (EnrichissementIndisponibleException $exception) {
            $messages = $exception->getMessage();
            for ($e = $exception->getPrevious(); null !== $e; $e = $e->getPrevious()) {
                $messages .= ' '.$e->getMessage();
            }
            self::assertStringNotContainsString('test-key', $messages);
        }
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
