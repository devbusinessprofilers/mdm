<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Import\Legacy\LegacyLovMapper;
use PHPUnit\Framework\TestCase;

final class LegacyLovMapperTest extends TestCase
{
    private LegacyLovMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LegacyLovMapper();
    }

    public function testClassificationStarsMapToHotelTypologies(): void
    {
        self::assertSame(['GENERALE_TYPOLOGIE_3'], $this->mapper->typologies('★★★★', 'Hôtel', '')['codes']);
        self::assertSame(['GENERALE_TYPOLOGIE_5'], $this->mapper->typologies('Palace', 'Hôtel', '')['codes']);
    }

    public function testCentreDeCongresGetsItsTypology(): void
    {
        self::assertSame(['GENERALE_TYPOLOGIE_20'], $this->mapper->typologies('', 'Centre de congrès', '')['codes']);
    }

    public function testTypeLieuxLabelsAndAliasesAreResolved(): void
    {
        $result = $this->mapper->typologies('', 'Lieu', json_encode([
            'Châteaux / Domaines',
            'Circuits Automobiles / Kartings',
            'Résidence / Appart’hotel',
            'Avec Hébergements',
        ], JSON_THROW_ON_ERROR));
        self::assertSame(['GENERALE_TYPOLOGIE_6', 'GENERALE_TYPOLOGIE_21', 'GENERALE_TYPOLOGIE_13'], $result['codes']);
        self::assertTrue($result['hebergement']);
        self::assertSame([], $result['warnings']);
    }

    public function testUnknownTypeLieuFallsBackToAutresWithWarning(): void
    {
        $result = $this->mapper->typologies('', 'Lieu', '["Type inconnu du catalogue"]');
        self::assertSame(['GENERALE_TYPOLOGIE_40'], $result['codes']);
        self::assertContains('type_lieu_non_mappe', $result['warnings']);
    }

    public function testThemesSplitBetweenCadreEnvAndThematiques(): void
    {
        $result = $this->mapper->themes('["Mer","Au vert","Eco-responsable","RSE","Châteaux","Pas de Thème","Ile"]');
        self::assertSame(['TA_CADRE_ENV_1', 'TA_CADRE_ENV_2'], $result['cadreEnv']);
        self::assertSame(['TA_THEMATIQUE_3', 'TA_THEMATIQUE_7'], $result['thematiques']);
        self::assertSame([], $result['warnings']);
    }

    public function testInvalidThemeJsonProducesWarning(): void
    {
        self::assertContains('thematique_json_invalide', $this->mapper->themes('pas du json')['warnings']);
    }

    public function testCountryCodes(): void
    {
        self::assertSame('FR', $this->mapper->countryCode('France'));
        self::assertSame('FR', $this->mapper->countryCode('DOM TOM'));
        self::assertSame('GB', $this->mapper->countryCode('Royaume-Uni'));
        self::assertNull($this->mapper->countryCode('Atlantide'));
    }
}
