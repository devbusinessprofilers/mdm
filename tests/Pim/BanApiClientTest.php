<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\BanApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BanApiClientTest extends TestCase
{
    public function testParseLaReponseCsvDeLaBan(): void
    {
        $csv = implode("\n", [
            'id,adresse,code_postal,ville,latitude,longitude,result_label,result_score,result_type,result_postcode,result_city',
            '761,"BP 24 Fontevraud-l\'Abbaye",49590,Fontevraud-l\'Abbaye,47.18,0.05,"Fontevraud-l\'Abbaye",0.92,municipality,49590,Fontevraud-l\'Abbaye',
            '999,"Nulle part",00000,Introuvable,,,,,,,',
        ]);
        $client = new BanApiClient(new MockHttpClient(new MockResponse($csv)), 'https://ban.invalid');

        $resultats = $client->verifierLot([
            ['id' => '761', 'adresse' => "BP 24 Fontevraud-l'Abbaye", 'codePostal' => '49590', 'ville' => "Fontevraud-l'Abbaye"],
            ['id' => '999', 'adresse' => 'Nulle part', 'codePostal' => '00000', 'ville' => 'Introuvable'],
        ]);

        self::assertSame(0.92, $resultats['761']['score']);
        self::assertSame('49590', $resultats['761']['codePostal']);
        self::assertSame('47.18', $resultats['761']['latitude']);
        self::assertNull($resultats['999']['score']);
        self::assertNull($resultats['999']['label']);
    }

    public function testErreurHttpLisible(): void
    {
        $client = new BanApiClient(new MockHttpClient(new MockResponse('', ['http_code' => 503])), 'https://ban.invalid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 503');
        $client->verifierLot([['id' => '1', 'adresse' => 'x', 'codePostal' => '', 'ville' => 'y']]);
    }

    public function testLotVideSansAppel(): void
    {
        $client = new BanApiClient(new MockHttpClient(), 'https://ban.invalid');

        self::assertSame([], $client->verifierLot([]));
    }
}
