<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Service\GeoapifyClient;
use App\Pim\Service\RestaurantAttributsVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RestaurantAttributsVerifierTest extends TestCase
{
    public function testProposeLesAttributsAbsents(): void
    {
        $restaurant = self::restaurant();
        $propositions = self::verifier([
            'cuisine' => 'italian;pizza',
            'diet:vegetarian' => 'yes',
            'wheelchair' => 'yes',
            'outdoor_seating' => 'yes',
            'internet_access' => 'wlan',
            'website' => 'https://trattoria.example',
        ])->analyser($restaurant);

        $parChamp = [];
        foreach ($propositions as $proposition) {
            $parChamp[$proposition->champ] = $proposition;
        }
        self::assertArrayHasKey('restaurant_types_cuisine', $parChamp);
        self::assertSame(['ITALIEN'], $parChamp['restaurant_types_cuisine']->payload['codes']);
        self::assertArrayHasKey('restaurant_specificites', $parChamp);
        self::assertSame(['VEGETARIENNES'], $parChamp['restaurant_specificites']->payload['codes']);
        self::assertArrayHasKey('restaurant_equipements', $parChamp);
        self::assertSame(['WIFI', 'TERRASSE'], $parChamp['restaurant_equipements']->payload['codes']);
        self::assertArrayHasKey('restaurant_acces_pmr', $parChamp);
        self::assertTrue($parChamp['restaurant_acces_pmr']->payload['bool']);
        self::assertArrayHasKey('restaurant_site_officiel', $parChamp);
        self::assertSame('https://trattoria.example', $parChamp['restaurant_site_officiel']->valeurProposee);
    }

    public function testNeProposeRienQuandDejaRenseigne(): void
    {
        $restaurant = self::restaurant();
        $restaurant->changeAccesPmr(true);
        $restaurant->changeSiteOfficiel('https://deja.example');

        // OSM ne renvoie que des champs déjà remplis → aucune proposition.
        $propositions = self::verifier([
            'wheelchair' => 'yes',
            'website' => 'https://autre.example',
        ])->analyser($restaurant);

        self::assertSame([], $propositions);
    }

    public function testIgnoreUnRestaurantSansGps(): void
    {
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Sans GPS');
        $restaurant->changeLocalisation(new Localisation());

        // Aucun appel HTTP sans coordonnées.
        $client = new GeoapifyClient(new MockHttpClient(static function (): MockResponse {
            self::fail('Geoapify ne doit pas être interrogé sans GPS.');
        }), 'https://geoapify.example', 'test-key');

        self::assertSame([], (new RestaurantAttributsVerifier($client))->analyser($restaurant));
    }

    /** @param array<string, string> $rawTags */
    private static function verifier(array $rawTags): RestaurantAttributsVerifier
    {
        $client = new GeoapifyClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
                'features' => [['properties' => ['datasource' => ['raw' => $rawTags]]]],
            ], JSON_THROW_ON_ERROR))),
            'https://geoapify.example',
            'test-key',
        );

        return new RestaurantAttributsVerifier($client);
    }

    private static function restaurant(): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Trattoria');
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeLatitude('48.8566');
        $localisation->changeLongitude('2.3522');
        $restaurant->changeLocalisation($localisation);

        return $restaurant;
    }
}
