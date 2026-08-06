<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Enum\ModeInterventionService;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\Legacy\LegacyLovMapper;
use App\Pim\Import\Legacy\LegacyServiceLovMapper;
use App\Pim\Import\Legacy\LegacyServiceRowMapper;
use PHPUnit\Framework\TestCase;

final class LegacyServiceRowMapperTest extends TestCase
{
    private LegacyServiceRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LegacyServiceRowMapper(new LegacyLovMapper(), new LegacyServiceLovMapper());
    }

    public function testMobileServiceIsFullyMapped(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '5100',
            'Publié / non publié' => 'true',
            'Nom Français' => 'Traiteur des Halles',
            'Gamme' => 'Prestataires de service',
            'Type de prestataire' => 'Traiteurs',
            'Categorie' => 'RSE',
            'Description générale' => 'Traiteur événementiel.',
            'Tarifs activité à partir de' => '35',
            "Rayon d'action (Région)" => "Île-de-France\nNormandie",
            "Rayon d'action géographique (département)" => 'Paris',
            'Lien youtube' => 'https://youtube.com/watch?v=t',
            'Pays' => 'France',
            'Ville' => 'Paris',
            'Les plus (sous forme de Bullet point) - 1' => 'Bio et local',
            'Photos' => '{"master":["x/master/1.jpg"]}',
        ]));

        self::assertSame(5100, $mapped->syspadId);
        self::assertTrue($mapped->publish);
        self::assertSame('Prestataires de service', $mapped->gamme);
        self::assertNotNull($mapped->photosJson);
        self::assertContains('atouts_non_mappes', $mapped->warnings);

        $service = $mapped->service;
        self::assertSame(5100, $service->fiche()->code());
        self::assertSame('Traiteur des Halles', $service->fiche()->label());
        self::assertSame(['TS_TRAITEUR'], $service->prestations());
        self::assertTrue($service->demarcheRse());
        self::assertNull($service->prestataireEsat());
        self::assertSame('Traiteur événementiel.', $service->descriptionGenerale());
        self::assertSame(35.0, $service->tarifParPrestation());
        self::assertNull($service->tarifParPersonne());
        self::assertSame(ModeInterventionService::Mobile, $service->modeIntervention());
        self::assertSame(['Île-de-France', 'Normandie'], $service->regionsMobiles());
        self::assertSame(['Paris'], $service->departementsMobiles());
        self::assertSame('FR', $service->localisation()?->countryCode());
    }

    public function testFixedServiceWithoutTypeGetsWarnings(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '5200',
            'Publié / non publié' => 'false',
            'Nom Français' => 'Prestataire mystère',
            'Gamme' => 'Prestataires de service',
            'Categorie' => 'ESAT / STPA',
            'Tarifs activité à partir de' => '0',
            'Ville' => 'Lyon',
        ]));

        $service = $mapped->service;
        self::assertSame([], $service->prestations());
        self::assertContains('type_prestataire_absent', $mapped->warnings);
        self::assertTrue($service->prestataireEsat());
        self::assertNull($service->tarifParPrestation());
        self::assertSame(ModeInterventionService::Fixe, $service->modeIntervention());
    }

    public function testUnknownTypeProducesWarning(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '5300',
            'Nom Français' => 'X',
            'Gamme' => 'Prestataires de service',
            'Type de prestataire' => 'Dresseurs de licornes',
        ]));
        self::assertSame([], $mapped->service->prestations());
        self::assertContains('type_prestataire_non_mappe', $mapped->warnings);
    }

    public function testSupportsOnlyPrestatairesDeService(): void
    {
        self::assertTrue($this->mapper->supports($this->row(['Gamme' => 'Prestataires de service'])));
        self::assertFalse($this->mapper->supports($this->row(['Gamme' => 'Idée'])));
    }

    /** @param array<string, string> $cells */
    private function row(array $cells): RawCsvRow
    {
        return new RawCsvRow(1, $cells);
    }
}
