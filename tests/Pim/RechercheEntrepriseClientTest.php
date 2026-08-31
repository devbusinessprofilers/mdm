<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\EnrichissementIndisponibleException;
use App\Pim\Service\RechercheEntrepriseClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RechercheEntrepriseClientTest extends TestCase
{
    public function testRetriesWithoutCodePostalWhenFilteredSearchIsEmpty(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return 1 === count($requests)
                ? new MockResponse('{"results": []}')
                : new MockResponse(json_encode(['results' => [[
                    'nom_complet' => 'BUSINESS PROFILERS (BP)',
                    'nom_raison_sociale' => 'BUSINESS PROFILERS',
                    'siren' => '480674100',
                    'dirigeants' => [
                        ['type_de_dirigeant' => 'personne morale', 'denomination' => 'HOLDING BP'],
                        ['type_de_dirigeant' => 'personne physique', 'nom' => 'DURAND', 'prenoms' => 'JEAN, MARIE', 'qualite' => 'Président'],
                    ],
                    'siege' => [
                        'siret' => '48067410000031',
                        'numero_voie' => '1',
                        'type_voie' => 'AVENUE',
                        'libelle_voie' => 'DU GENERAL DE GAULLE',
                        'code_postal' => '60500',
                        'libelle_commune' => 'CHANTILLY',
                        'latitude' => '49.19',
                        'longitude' => '2.46',
                    ],
                ]]], JSON_THROW_ON_ERROR));
        });
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $info = $client->findBest('Business Profilers', '60460');

        self::assertCount(2, $requests);
        self::assertStringContainsString('code_postal=60460', $requests[0]);
        self::assertStringNotContainsString('code_postal', $requests[1]);
        self::assertNotNull($info);
        // Résultat obtenu en repli France entière : le flag le signale.
        self::assertTrue($info->rapprochementSansCodePostal);
        // Adresse et personnes en nom propre ; la raison sociale reste en capitales (Sirene).
        self::assertSame('1 Avenue du General de Gaulle', $info->rue);
        self::assertSame('FR39480674100', $info->numeroTva);
        self::assertSame('BUSINESS PROFILERS', $info->raisonSociale);
        // Seul le premier dirigeant personne physique est retenu, avec son prénom usuel.
        self::assertSame('Jean', $info->dirigeantPrenom);
        self::assertSame('Durand', $info->dirigeantNom);
    }

    public function testReturnsNullOnTransportError(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'DNS failure']));
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        self::assertNull($client->findBest('Business Profilers', null));
    }

    public function testFindStatutLitLEtatFermeSansFiltrerLesActifs(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse(json_encode(['results' => [[
                'nom_complet' => 'HOTEL FERME',
                'siren' => '480674100',
                'etat_administratif' => 'C',
                'matching_etablissements' => [
                    ['siret' => '48067410000031', 'etat_administratif' => 'F'],
                ],
                'siege' => ['siret' => '48067410000048', 'etat_administratif' => 'A'],
            ]]], JSON_THROW_ON_ERROR));
        });
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $info = $client->findStatut('480 674 100 00031');

        self::assertNotNull($info);
        // L'établissement précis (matching_etablissements), pas le siège, fait foi.
        self::assertSame('F', $info->etatAdministratif);
        self::assertTrue($info->estCesse());
        self::assertStringNotContainsString('etat_administratif=A', $requests[0]);
    }

    public function testFindStatutRejetteUnSiretInvalide(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"results": []}'));
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        self::assertNull($client->findStatut('123'));
    }

    public function testFindStatutPropageLIndisponibilite(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'DNS failure']));
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $this->expectException(EnrichissementIndisponibleException::class);
        $client->findStatut('48067410000031');
    }

    public function testFindBestPropageLIndisponibiliteQuandDemande(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'DNS failure']));
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $this->expectException(EnrichissementIndisponibleException::class);
        $client->findBest('Business Profilers', null, absorberIndisponibilite: false);
    }

    public function testRetenteUneSeuleFoisApresUn429(): void
    {
        $reponses = [
            new MockResponse('', ['http_code' => 429, 'response_headers' => ['retry-after' => '1']]),
            new MockResponse(json_encode(['results' => [[
                'siren' => '480674100',
                'siege' => ['siret' => '48067410000031'],
            ]]], JSON_THROW_ON_ERROR)),
        ];
        $client = new RechercheEntrepriseClient(new MockHttpClient($reponses), new NullLogger(), 'https://recherche.example');

        $info = $client->findBest('Business Profilers', null);

        self::assertNotNull($info);
        self::assertSame('48067410000031', $info->siret);
    }

    public function testLesSuggestionsDAdresseSuiventLesChampsDeLocalisation(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url): MockResponse {
            self::assertStringContainsString('etat_administratif=A', $url);
            self::assertStringContainsString('per_page=3', $url);

            return new MockResponse(json_encode(['results' => [
                [
                    'nom_complet' => 'BUSINESS PROFILERS',
                    'siege' => [
                        'adresse' => '1 AVENUE DU GENERAL DE GAULLE 60500 CHANTILLY',
                        'numero_voie' => '1',
                        'type_voie' => 'AVENUE',
                        'libelle_voie' => 'DU GENERAL DE GAULLE',
                        'code_postal' => '60500',
                        'libelle_commune' => 'CHANTILLY',
                        'departement' => '60',
                        'region' => '32',
                        'latitude' => '49.1974',
                        'longitude' => '2.4623',
                    ],
                ],
                [
                    'nom_complet' => 'AUBERGE DE L\'ECLUSE',
                    'siege' => [
                        'adresse' => 'CHEMIN DE L\'ECLUSE 78170 LA CELLE-SAINT-CLOUD',
                        'type_voie' => 'CHEMIN',
                        'libelle_voie' => 'DE L\'ECLUSE',
                        'code_postal' => '78170',
                        'libelle_commune' => 'LA CELLE-SAINT-CLOUD',
                        'departement' => '78',
                    ],
                ],
                // Sans adresse de siège : inutilisable pour remplir la fiche.
                ['nom_complet' => 'SANS ADRESSE', 'siege' => []],
            ]], JSON_THROW_ON_ERROR));
        });
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $suggestions = $client->suggestionsAdresse('Business Profilers');

        // Capitales de l'annuaire remises en nom propre (particules minuscules).
        self::assertSame([
            'label' => 'BUSINESS PROFILERS — 1 Avenue du General de Gaulle 60500 Chantilly',
            'ruePostale' => '1 Avenue du General de Gaulle',
            'codePostal' => '60500',
            'ville' => 'Chantilly',
            'region' => null,
            // Numéro INSEE traduit en libellé ; la région ne sort qu'en code.
            'departement' => 'Oise',
            'pays' => 'France',
            'countryCode' => 'FR',
            'latitude' => '49.1974',
            'longitude' => '2.4623',
        ], $suggestions[0] ?? null);
        self::assertSame('Chemin de l\'Ecluse', $suggestions[1]['ruePostale'] ?? null);
        self::assertSame('La Celle-Saint-Cloud', $suggestions[1]['ville'] ?? null);
        // Libellé composé champ par champ : l'article de la commune garde sa majuscule.
        self::assertSame('AUBERGE DE L\'ECLUSE — Chemin de l\'Ecluse 78170 La Celle-Saint-Cloud', $suggestions[1]['label'] ?? null);
        self::assertSame('Yvelines', $suggestions[1]['departement'] ?? null);
        self::assertCount(2, $suggestions);
    }

    public function testFindStatutRendEtatInconnuQuandLeSiretNEstPasDansLaReponse(): void
    {
        // Siège fermé mais SIRET demandé absent de la réponse : ne pas
        // extrapoler l'état d'un autre établissement.
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['results' => [[
            'siren' => '480674100',
            'etat_administratif' => 'C',
            'matching_etablissements' => [['siret' => '48067410000099', 'etat_administratif' => 'F']],
            'siege' => ['siret' => '48067410000048', 'etat_administratif' => 'F'],
        ]]], JSON_THROW_ON_ERROR)));
        $client = new RechercheEntrepriseClient($httpClient, new NullLogger(), 'https://recherche.example');

        $info = $client->findStatut('48067410000031');

        self::assertNotNull($info);
        self::assertNull($info->etatAdministratif);
        self::assertFalse($info->estCesse());
    }
}
