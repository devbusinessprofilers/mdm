<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\Legacy\LegacyLieuRowMapper;
use App\Pim\Import\Legacy\LegacyLovMapper;
use PHPUnit\Framework\TestCase;

final class LegacyLieuRowMapperTest extends TestCase
{
    private LegacyLieuRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LegacyLieuRowMapper(new LegacyLovMapper());
    }

    public function testFullRowIsMappedToAHydratedLieu(): void
    {
        $salleJson = json_encode([
            '1104' => [
                'Nom' => 'Salle 1',
                'Superficie Salle en m2' => '120',
                'Capacité en réunion' => '30',
                'Capacité en U' => '',
                'Capacité en Grande Ecole' => '0',
                'Capacité en Théâtre / Conférence' => '80',
                'Capacité en Cabaret' => '',
                'Capacité en Banquet' => '',
                'Capacité en Réception / Cocktail' => '100',
                'Capacité en Auditorium' => '',
                'Lumière du jour' => '1',
                'Accès PMR' => '0',
                'Dansant' => 'true',
            ],
            '1105' => ['Nom' => ''],
        ], JSON_THROW_ON_ERROR);

        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '256',
            'Publié / non publié' => 'true',
            'Nom Français' => 'Le Café de Paris',
            'Gamme' => 'Hôtel',
            'Classification' => '★★★★',
            'Thématique' => '["Mer","Esat","RSE"]',
            'Nombre de chambres' => '19',
            'dont nombre de chambres twin' => '5',
            'Nombre de chambres single' => '0',
            "Capacité d'accueil totale des personnes hebergés" => '24',
            'Nombre de salle(s) de réunion(s)' => '1',
            'Capacité de la plus grande salle en configuration cocktail (nb de pers)' => '100',
            "Capacité d'accueil de la plus grande en configuration théâtre/conférence (nb pers)" => '80',
            'Surface de la plus grande salle de réunion  (en m²)' => '120',
            'Pays' => 'France',
            'Région' => 'Nouvelle-Aquitaine',
            'Département' => 'Pyrénées-Atlantiques',
            'Adresse postale' => '5, place Bellevue',
            'Code postal' => '64200',
            'Ville' => 'Biarritz',
            'Latitude - GPS' => '43.48790208138496',
            'Longitude - GPS' => '-1.562134882514921',
            'Salle' => $salleJson,
            'Nom aéroport 1' => 'Aéroport de Biarritz',
            'Distance (km) aéroport 1' => '4',
            'Nom de la gare 1' => 'Gare de Biarritz',
            'Distance (km) gare 1' => '4',
            'Nom de la ville' => 'Biarritz',
            'Distance (km)' => '0',
            'Ligne Metro' => 'Ligne 1',
            'Arret Metro' => 'Mairie',
            'Description générale' => 'Une description courte.',
            'Hébergement' => 'Chambres avec vue.',
            'Salles de séminaires' => 'Salle lumineuse.',
            'Restauration / Gastronomie' => 'Cuisine basque.',
            'Loisirs 1' => 'Surf',
            'Court de tennis' => 'true',
            'Les plus (sous forme de Bullet point) - 1' => 'Vue océan',
            "Journée d'étude à partir de xx € /pers" => '55',
            'Séminaire résidentiel à partir de xx €/pers' => '0',
            'Wifi' => 'true',
            'Piscine Intérieure' => 'true',
            'Accès PMR' => 'true',
            'Photos' => '{"master":["x/master/1.jpg"]}',
        ]));

        self::assertSame(256, $mapped->syspadId);
        self::assertTrue($mapped->publish);
        self::assertSame('Hôtel', $mapped->gamme);
        self::assertNotNull($mapped->photosJson);
        self::assertContains('restauration_non_mappee', $mapped->warnings);

        $lieu = $mapped->lieu;
        self::assertSame('Le Café de Paris', $lieu->fiche()->label());
        self::assertSame(['GENERALE_TYPOLOGIE_3', 'GENERALE_TYPOLOGIE_11'], $lieu->generaleTypologie());
        self::assertSame(['TA_CADRE_ENV_1'], $lieu->taCadreEnv());
        self::assertSame(19, $lieu->chambreNbTotal());
        self::assertTrue($lieu->chambreHebergement());
        self::assertNull($lieu->chambreNbTotalSingle());
        self::assertSame(1, $lieu->salleReunionNbTotal());
        self::assertTrue($lieu->salleReunionExist());
        self::assertSame('Une description courte.', $lieu->descGenerale());
        self::assertSame('Vue océan', $lieu->atout1());
        self::assertSame(['Surf', 'Court de tennis'], $lieu->loisirInterne());
        self::assertSame(['BIEN_ETRE_2'], $lieu->bienEtre());
        self::assertSame(['TECHNIQUE_REUNION_1'], $lieu->techniqueReunion());
        self::assertTrue($lieu->pmrAcces());
        self::assertTrue($lieu->demarcheRse());
        self::assertSame('55', $lieu->tarification()->seminaireJourneeJourneeEtude());

        $localisation = $lieu->fiche()->localisation();
        self::assertNotNull($localisation);
        self::assertSame('FR', $localisation->countryCode());
        self::assertSame('Biarritz', $localisation->ville());
        self::assertSame('43.4879021', $localisation->latitude());

        $salles = $lieu->salles();
        self::assertCount(1, $salles);
        $salle = $salles->first() ?: self::fail('Le lieu doit avoir une salle.');
        self::assertSame('Salle 1', $salle->nom());
        self::assertSame(120, $salle->superficie());
        self::assertSame(30, $salle->capaciteReunion());
        self::assertNull($salle->capaciteClasse());
        self::assertSame(80, $salle->capaciteTheatre());
        self::assertSame(100, $salle->capaciteCocktail());
        self::assertTrue($salle->lumiereJour());
        self::assertFalse($salle->accesPmr());
        self::assertTrue($salle->espaceDansant());

        $acces = $lieu->acces();
        self::assertCount(4, $acces); // aéroport 1 + gare + grande ville + métro
        $types = array_map(static fn ($item) => $item->type(), $acces->toArray());
        self::assertContains(TypeAccesLieu::Aeroport, $types);
        self::assertContains(TypeAccesLieu::Gare, $types);
        self::assertContains(TypeAccesLieu::GrandeVille, $types);
        self::assertContains(TypeAccesLieu::Metro, $types);
    }

    public function testLongDescriptionsAndAtoutsAreTruncatedWithWarnings(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '1',
            'Nom Français' => 'Lieu',
            'Gamme' => 'Lieu',
            'Description générale' => str_repeat('a', 1200),
            'Les plus (sous forme de Bullet point) - 1' => str_repeat('b', 60),
        ]));
        self::assertSame(1000, mb_strlen((string) $mapped->lieu->descGenerale()));
        self::assertSame(35, mb_strlen((string) $mapped->lieu->atout1()));
        self::assertContains('desc_generale_tronquee', $mapped->warnings);
        self::assertContains('atout_tronque', $mapped->warnings);
    }

    public function testInvalidSyspadOrNameIsRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->mapper->map($this->row(['Id syspad' => 'abc', 'Nom Français' => 'X', 'Gamme' => 'Hôtel']));
    }

    public function testSupportsFiltersOnGamme(): void
    {
        self::assertTrue($this->mapper->supports($this->row(['Gamme' => 'Hôtel'])));
        self::assertFalse($this->mapper->supports($this->row(['Gamme' => 'Restaurant'])));
    }

    public function testInvalidGpsProducesWarningInsteadOfFailure(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '2',
            'Nom Français' => 'Lieu GPS',
            'Gamme' => 'Lieu',
            'Latitude - GPS' => '999',
            'Longitude - GPS' => 'n/a',
        ]));
        self::assertContains('gps_invalide', $mapped->warnings);
        self::assertNull($mapped->lieu->fiche()->localisation()?->latitude());
    }

    /** @param array<string, string> $cells */
    private function row(array $cells): RawCsvRow
    {
        return new RawCsvRow(1, $cells);
    }
}
