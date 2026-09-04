<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Localisation;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\AeroportReferenceRepository;
use App\Pim\Repository\GrandeVilleReferenceRepository;
use App\Pim\Service\AccesSuggesteur;
use App\Pim\Service\GeoapifyClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AccesSuggesteurTest extends TestCase
{
    public function testSansCoordonneesGpsLaSuggestionEstRefusee(): void
    {
        $suggesteur = $this->suggesteur(null, null, new MockHttpClient(), '');

        $this->expectException(\DomainException::class);
        $suggesteur->suggerer($this->fiche(null), [], true);
    }

    public function testAvecGeoapifyChaqueTypeSortUneEntreeItineraireCompris(): void
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (str_contains($url, '/v2/places')) {
                return new MockResponse((string) json_encode(['features' => [
                    ['properties' => ['name' => 'Bastille', 'lat' => 48.853, 'lon' => 2.369, 'distance' => 300, 'categories' => ['public_transport', 'public_transport.subway']]],
                    // Station de métro taguée aussi « train » (vu sur données
                    // réelles) : jamais retenue comme gare.
                    ['properties' => ['name' => 'Châtelet', 'lat' => 48.858, 'lon' => 2.347, 'distance' => 400, 'categories' => ['public_transport', 'public_transport.subway', 'public_transport.train']]],
                    ['properties' => ['name' => 'Gare de Lyon', 'lat' => 48.844, 'lon' => 2.373, 'distance' => 1200, 'categories' => ['public_transport', 'public_transport.train']]],
                    // Tram à 3 km : hors périmètre piéton, jamais suggéré.
                    ['properties' => ['name' => 'Porte de Vincennes', 'lat' => 48.847, 'lon' => 2.410, 'distance' => 3000, 'categories' => ['public_transport', 'public_transport.tram']]],
                ]]));
            }

            return new MockResponse((string) json_encode(['features' => [
                ['properties' => ['distance' => 25300, 'time' => 1320]],
            ]]));
        });
        $suggesteur = $this->suggesteur(
            ['nom' => 'Charles de Gaulle International Airport', 'codeIata' => 'CDG', 'latitude' => 49.0097, 'longitude' => 2.5479, 'distanceKm' => 23.4],
            ['nom' => 'Lille', 'population' => 236_000, 'latitude' => 50.63, 'longitude' => 3.06, 'distanceKm' => 204.0],
            $http,
            'cle-de-test',
        );

        $suggestions = $suggesteur->suggerer($this->fiche(['48.8566', '2.3522']), [], true);

        self::assertSame(
            ['aeroport', 'gare', 'metro', 'grande_ville'],
            array_map(static fn ($s): string => $s->type, $suggestions),
        );
        [$aeroport, $gare, $metro] = $suggestions;
        self::assertSame('Charles de Gaulle International Airport (CDG)', $aeroport->nom);
        self::assertSame('25', $aeroport->distanceKilometres);
        self::assertSame(22, $aeroport->dureeMinutes);
        self::assertSame('Voiture', $aeroport->modeTransport);
        self::assertSame('Gare de Lyon', $gare->nom);
        self::assertSame('À pied', $gare->modeTransport);
        self::assertSame('Bastille', $metro->nom);
        self::assertSame('À pied', $metro->modeTransport);
    }

    /** Gamme Service : route / parking / gare / aéroport, jamais métro ni tram. */
    public function testLesTypesDuServiceRemplacentMetroEtTramParLeParking(): void
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (str_contains($url, '/v2/places')) {
                self::assertStringContainsString('categories=public_transport.train%2Cparking', $url);
                self::assertStringNotContainsString('subway', $url);

                return new MockResponse((string) json_encode(['features' => [
                    ['properties' => ['name' => 'Bastille', 'lat' => 48.853, 'lon' => 2.369, 'distance' => 300, 'categories' => ['public_transport', 'public_transport.subway']]],
                    ['properties' => ['name' => 'Parking Saemes', 'lat' => 48.855, 'lon' => 2.355, 'distance' => 250, 'categories' => ['parking', 'parking.cars']]],
                    ['properties' => ['name' => 'Gare de Lyon', 'lat' => 48.844, 'lon' => 2.373, 'distance' => 1200, 'categories' => ['public_transport', 'public_transport.train']]],
                ]]));
            }

            return new MockResponse((string) json_encode(['features' => [['properties' => ['distance' => 800, 'time' => 600]]]]));
        });
        $suggesteur = $this->suggesteur(
            ['nom' => 'Orly', 'codeIata' => 'ORY', 'latitude' => 48.72, 'longitude' => 2.37, 'distanceKm' => 15.0],
            ['nom' => 'Lille', 'population' => 236_000, 'latitude' => 50.63, 'longitude' => 3.06, 'distanceKm' => 204.0],
            $http,
            'cle-de-test',
        );

        $suggestions = $suggesteur->suggerer($this->fiche(['48.8566', '2.3522']), [], false, AccesSuggesteur::TYPES_SERVICE);

        self::assertSame(['aeroport', 'gare', 'parking', 'grande_ville'], array_map(static fn ($s): string => $s->type, $suggestions));
        self::assertSame('Parking Saemes (0,3 km)', $suggestions[2]->nom);
    }

    public function testSansCleGeoapifyLesReferentielsStatiquesServentSeulsAvecRepliVolOiseau(): void
    {
        $suggesteur = $this->suggesteur(
            null,
            ['nom' => 'Lyon', 'population' => 522_000, 'latitude' => 45.76, 'longitude' => 4.83, 'distanceKm' => 56.2],
            new MockHttpClient(),
            '',
        );

        $suggestions = $suggesteur->suggerer($this->fiche(['45.5', '4.5']), ['aeroport'], true);

        self::assertCount(1, $suggestions);
        self::assertSame('grande_ville', $suggestions[0]->type);
        self::assertSame('Lyon', $suggestions[0]->nom);
        self::assertSame('56', $suggestions[0]->distanceKilometres);
        self::assertNull($suggestions[0]->dureeMinutes);
        self::assertSame('Voiture', $suggestions[0]->modeTransport);
    }

    public function testLaGrandeVilleOuSeTrouveDejaLaFicheNestPasSuggeree(): void
    {
        $suggesteur = $this->suggesteur(
            null,
            ['nom' => 'Paris', 'population' => 2_100_000, 'latitude' => 48.8566, 'longitude' => 2.3522, 'distanceKm' => 1.2],
            new MockHttpClient(),
            '',
        );

        self::assertSame([], $suggesteur->suggerer($this->fiche(['48.86', '2.35']), ['aeroport'], true));
    }

    public function testSansDistancesLeVolOiseauEstGlisseDansLeNom(): void
    {
        $suggesteur = $this->suggesteur(
            ['nom' => 'Marseille Provence Airport', 'codeIata' => 'MRS', 'latitude' => 43.44, 'longitude' => 5.22, 'distanceKm' => 5.3],
            null,
            new MockHttpClient(),
            '',
        );

        $suggestions = $suggesteur->suggerer($this->fiche(['43.4', '5.25']), [], false);

        self::assertCount(1, $suggestions);
        self::assertSame('Marseille Provence Airport (MRS) (5,3 km)', $suggestions[0]->nom);
        self::assertNull($suggestions[0]->distanceKilometres);
        self::assertNull($suggestions[0]->modeTransport);
    }

    /**
     * @param array{nom: string, codeIata: ?string, latitude: float, longitude: float, distanceKm: float}|null $aeroport
     * @param array{nom: string, population: int, latitude: float, longitude: float, distanceKm: float}|null   $ville
     */
    private function suggesteur(?array $aeroport, ?array $ville, MockHttpClient $http, string $apiKey): AccesSuggesteur
    {
        $aeroports = $this->createStub(AeroportReferenceRepository::class);
        $aeroports->method('plusProche')->willReturn($aeroport);
        $villes = $this->createStub(GrandeVilleReferenceRepository::class);
        $villes->method('plusProche')->willReturn($ville);

        return new AccesSuggesteur($aeroports, $villes, new GeoapifyClient($http, 'https://api.geoapify.test', $apiKey, 0), new NullLogger());
    }

    /** @param array{0: string, 1: string}|null $gps */
    private function fiche(?array $gps): \App\Pim\Entity\Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Fiche de test');
        if (null !== $gps) {
            $localisation = new Localisation();
            $localisation->changeLatitude($gps[0]);
            $localisation->changeLongitude($gps[1]);
            $lieu->fiche()->changeLocalisation($localisation);
        }

        return $lieu->fiche();
    }
}
