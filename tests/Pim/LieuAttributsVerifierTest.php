<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Service\GeoapifyClient;
use App\Pim\Service\LieuAttributsVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LieuAttributsVerifierTest extends TestCase
{
    public function testProposeLesAttributsAbsents(): void
    {
        $propositions = self::verifier([
            'stars' => '4',
            'brand' => 'Mercure',
            'website' => 'https://hotel.example',
            'phone' => '+33 1 23 45 67 89',
        ])->analyser(self::lieu());

        $parChamp = [];
        foreach ($propositions as $proposition) {
            $parChamp[$proposition->champ] = $proposition;
        }
        // 4 étoiles → « Hôtel 4 étoiles » (GENERALE_TYPOLOGIE_3).
        self::assertSame(['GENERALE_TYPOLOGIE_3'], $parChamp['lieu_lov_typologie']->payload['codes'] ?? null);
        self::assertSame('GENERALE_TYPOLOGIE', $parChamp['lieu_lov_typologie']->payload['attribut'] ?? null);
        self::assertSame('Mercure', $parChamp['lieu_chaine']->valeurProposee ?? null);
        self::assertSame('https://hotel.example', $parChamp['lieu_website']->valeurProposee ?? null);
        self::assertSame('+33 1 23 45 67 89', $parChamp['lieu_telephone']->valeurProposee ?? null);
    }

    public function testNeProposeRienQuandDejaRenseigne(): void
    {
        $lieu = self::lieu();
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_5']);
        $lieu->changeGeneraleChainesGroupeHot(['GENERALE_CHAINES_GROUPE_HOT_40']);
        $lieu->changeGeneraleWebsiteUrl('https://deja.example');
        $lieu->fiche()->changeTelephone('01 02 03 04 05');

        $propositions = self::verifier([
            'stars' => '4',
            'brand' => 'Mercure',
            'website' => 'https://autre.example',
            'phone' => '+33 9 87 65 43 21',
        ])->analyser($lieu);

        self::assertSame([], $propositions);
    }

    public function testUneEtoileHorsListeNeProposePasDeTypologie(): void
    {
        // La liste commence à « Hôtel 2 étoiles » : 1 étoile n'a pas d'équivalent.
        $propositions = self::verifier(['stars' => '1'])->analyser(self::lieu());

        self::assertSame([], $propositions);
    }

    public function testIgnoreUnLieuSansGps(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Sans GPS');
        $lieu->changeLocalisation(new Localisation());

        // Aucun appel HTTP sans coordonnées.
        $client = new GeoapifyClient(new MockHttpClient(static function (): MockResponse {
            self::fail('Geoapify ne doit pas être interrogé sans GPS.');
        }), 'https://geoapify.example', 'test-key');

        self::assertSame([], (new LieuAttributsVerifier($client))->analyser($lieu));
    }

    /** @param array<string, string> $rawTags */
    private static function verifier(array $rawTags): LieuAttributsVerifier
    {
        $client = new GeoapifyClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
                'features' => [['properties' => ['name' => 'Hôtel des Tests', 'datasource' => ['raw' => $rawTags]]]],
            ], JSON_THROW_ON_ERROR))),
            'https://geoapify.example',
            'test-key',
        );

        return new LieuAttributsVerifier($client);
    }

    private static function lieu(): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel des Tests');
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeLatitude('48.8566');
        $localisation->changeLongitude('2.3522');
        $lieu->changeLocalisation($localisation);

        return $lieu;
    }
}
