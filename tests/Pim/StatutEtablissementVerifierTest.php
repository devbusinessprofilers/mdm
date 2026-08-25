<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Localisation;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Service\RechercheEntrepriseClient;
use App\Pim\Service\StatutEtablissementVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StatutEtablissementVerifierTest extends TestCase
{
    public function testProposeLArchivageQuandLeSiretStockeEstCesse(): void
    {
        $lieu = self::lieuFrancais('Château cessé');
        $lieu->administratif()->changeInfoLegaleSiret('48067410000031');

        $propositions = self::verifier([[
            'siren' => '480674100',
            'matching_etablissements' => [['siret' => '48067410000031', 'etat_administratif' => 'F']],
        ]])->analyser($lieu);

        self::assertCount(1, $propositions);
        self::assertSame(SuggestionAction::Archiver, $propositions[0]->action);
        self::assertSame('statut', $propositions[0]->champ);
    }

    public function testAucuneSuggestionQuandLeSiretStockeEstActif(): void
    {
        $lieu = self::lieuFrancais('Hôtel actif');
        // TVA déjà présente : aucun backfill non plus.
        $lieu->administratif()->changeInfoLegaleSiret('48067410000031');
        $lieu->administratif()->changeInfoLegaleNumTva('FR39480674100');

        $propositions = self::verifier([[
            'siren' => '480674100',
            'matching_etablissements' => [['siret' => '48067410000031', 'etat_administratif' => 'A']],
        ]])->analyser($lieu);

        self::assertSame([], $propositions);
    }

    public function testProposeLeBackfillSiretQuandLeNomConcordeSansSiret(): void
    {
        $lieu = self::lieuFrancais('BUSINESS PROFILERS');

        $propositions = self::verifier([[
            'nom_complet' => 'BUSINESS PROFILERS',
            'nom_raison_sociale' => 'BUSINESS PROFILERS',
            'siren' => '480674100',
            'siege' => ['siret' => '48067410000031', 'etat_administratif' => 'A'],
        ]])->analyser($lieu);

        $champs = array_map(static fn ($p): string => $p->champ, $propositions);
        self::assertContains('info_legale_siret', $champs);
        self::assertContains('info_legale_num_tva', $champs);
    }

    public function testPenaliseLeBackfillObtenuSansAncrageCodePostal(): void
    {
        $lieu = self::lieuFrancais('BUSINESS PROFILERS');
        $requests = 0;
        $client = new RechercheEntrepriseClient(
            new MockHttpClient(function () use (&$requests): MockResponse {
                ++$requests;

                // Rien dans le code postal de la fiche : repli France entière.
                return 1 === $requests
                    ? new MockResponse('{"results": []}')
                    : new MockResponse(json_encode(['results' => [[
                        'nom_complet' => 'BUSINESS PROFILERS',
                        'siren' => '480674100',
                        'siege' => ['siret' => '48067410000031', 'etat_administratif' => 'A'],
                    ]]], JSON_THROW_ON_ERROR));
            }),
            new NullLogger(),
            'https://recherche.example',
        );

        $propositions = (new StatutEtablissementVerifier($client))->analyser($lieu);

        $siret = null;
        foreach ($propositions as $proposition) {
            if ('info_legale_siret' === $proposition->champ) {
                $siret = $proposition;
            }
        }
        self::assertNotNull($siret);
        // Nom identique (similarité 1.0) mais sans ancrage géographique : 0.9.
        self::assertEqualsWithDelta(0.9, $siret->score, 0.001);
    }

    public function testLaSimilariteIgnoreLesAccents(): void
    {
        $lieu = self::lieuFrancais('Hôtel Périgord');

        $propositions = self::verifier([[
            'nom_complet' => 'HOTEL PERIGORD',
            'siren' => '480674100',
            'siege' => ['siret' => '48067410000031', 'etat_administratif' => 'A'],
        ]])->analyser($lieu);

        $champs = array_map(static fn ($p): string => $p->champ, $propositions);
        self::assertContains('info_legale_siret', $champs);
    }

    public function testIgnoreLesLieuxEtrangers(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Hotel Berlin');
        $localisation = new Localisation();
        $localisation->changePays('Allemagne');
        $lieu->changeLocalisation($localisation);
        $lieu->administratif()->changeInfoLegaleSiret('48067410000031');

        // Aucun appel HTTP ne doit partir pour l'étranger.
        $client = new RechercheEntrepriseClient(
            new MockHttpClient(static function (): MockResponse {
                self::fail('Sirene ne doit pas être interrogée hors de France.');
            }),
            new NullLogger(),
            'https://recherche.example',
        );

        self::assertSame([], (new StatutEtablissementVerifier($client))->analyser($lieu));
    }

    /** @param list<array<string, mixed>> $results */
    private static function verifier(array $results): StatutEtablissementVerifier
    {
        $client = new RechercheEntrepriseClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['results' => $results], JSON_THROW_ON_ERROR))),
            new NullLogger(),
            'https://recherche.example',
        );

        return new StatutEtablissementVerifier($client);
    }

    private static function lieuFrancais(string $label): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeCodePostal('60500');
        $localisation->changeVille('Chantilly');
        $lieu->changeLocalisation($localisation);

        return $lieu;
    }
}
