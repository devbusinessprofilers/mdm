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
            'phone' => '+33 1 23 45 67 89',
        ])->analyser($restaurant);

        $parChamp = [];
        foreach ($propositions as $proposition) {
            $parChamp[$proposition->champ] = $proposition;
        }
        // Les codes dépendent du catalogue effectif (runtime BDD ou repli
        // statique selon l'ordre des tests) : on vérifie les LIBELLÉS résolus,
        // pas le schéma de codes.
        self::assertSame(['Italien'], self::libelles('TYPE_CUISINE', $parChamp['restaurant_types_cuisine'] ?? null));
        self::assertSame(['Végétariennes'], self::libelles('SPECIFICITE_ALIMENTAIRE', $parChamp['restaurant_specificites'] ?? null));
        self::assertSame(['Wifi', 'Terrasse extérieure'], self::libelles('EQUIPEMENT_RESTAURANT', $parChamp['restaurant_equipements'] ?? null));
        self::assertArrayHasKey('restaurant_acces_pmr', $parChamp);
        self::assertTrue($parChamp['restaurant_acces_pmr']->payload['bool'] ?? null);
        self::assertArrayHasKey('restaurant_site_officiel', $parChamp);
        self::assertSame('https://trattoria.example', $parChamp['restaurant_site_officiel']->valeurProposee);
        self::assertArrayHasKey('restaurant_telephone', $parChamp);
        self::assertSame('+33 1 23 45 67 89', $parChamp['restaurant_telephone']->valeurProposee);
    }

    public function testNeProposeRienQuandDejaRenseigne(): void
    {
        $restaurant = self::restaurant();
        $restaurant->changeAccesPmr(true);
        $restaurant->changeSiteOfficiel('https://deja.example');
        $restaurant->fiche()->changeTelephone('01 02 03 04 05');

        // OSM ne renvoie que des champs déjà remplis → aucune proposition.
        $propositions = self::verifier([
            'wheelchair' => 'yes',
            'website' => 'https://autre.example',
            'phone' => '+33 9 87 65 43 21',
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

    public function testIgnoreLeCommerceVoisinMalApparie(): void
    {
        // GPS imprécis : le POI au point est un autre commerce → aucune proposition.
        $propositions = self::verifier([
            'cuisine' => 'french',
            'website' => 'https://boulangerie.example',
        ], nomOsm: 'Boulangerie Dupont')->analyser(self::restaurant());

        self::assertSame([], $propositions);
    }

    /** @param array<string, string> $rawTags */
    private static function verifier(array $rawTags, string $nomOsm = 'Trattoria'): RestaurantAttributsVerifier
    {
        $client = new GeoapifyClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
                'features' => [['properties' => ['name' => $nomOsm, 'datasource' => ['raw' => $rawTags]]]],
            ], JSON_THROW_ON_ERROR))),
            'https://geoapify.example',
            'test-key',
        );

        return new RestaurantAttributsVerifier($client);
    }

    /** @return list<string> libellés des codes proposés, résolus contre le catalogue effectif */
    private static function libelles(string $attribut, ?\App\Pim\Service\SuggestionProposee $proposition): array
    {
        self::assertNotNull($proposition);
        $valeurs = \App\Pim\Lov\RestaurantLovCatalog::values($attribut);

        return array_map(
            static fn (string $code): string => $valeurs[$code] ?? $code,
            $proposition->payload['codes'] ?? [],
        );
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
