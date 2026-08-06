<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Enum\TypeAccesRestaurant;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\Legacy\LegacyLovMapper;
use App\Pim\Import\Legacy\LegacyRestaurantLovMapper;
use App\Pim\Import\Legacy\LegacyRestaurantRowMapper;
use PHPUnit\Framework\TestCase;

final class LegacyRestaurantRowMapperTest extends TestCase
{
    private LegacyRestaurantRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LegacyRestaurantRowMapper(new LegacyLovMapper(), new LegacyRestaurantLovMapper());
    }

    public function testFullRowIsMapped(): void
    {
        $salleJson = json_encode([
            '9' => ['Nom' => 'Salon privé', 'Superficie Salle en m2' => '45', 'Capacité en Banquet' => '30', 'Capacité en Réception / Cocktail' => '50', 'Lumière du jour' => '1', 'Accès PMR' => '1', 'Dansant' => '0'],
        ], JSON_THROW_ON_ERROR);

        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '7100',
            'Publié / non publié' => 'true',
            'Nom Français' => 'La Table du Port',
            'Gamme' => 'Restaurant',
            'Thématique' => '["Gastronomique","Mer","Esat","Pas de Thème","Oenotourisme"]',
            'Description générale' => 'Vue sur le port.',
            'Restauration / Gastronomie' => 'Cuisine de la mer.',
            'Les plus (sous forme de Bullet point) - 1' => 'Terrasse',
            'Les plus (sous forme de Bullet point) - 2' => 'Produits frais',
            'Capacité de la plus grande salle en configuration cocktail (nb de pers)' => '80',
            'Wifi' => 'true',
            'Accès PMR' => 'true',
            'Pays' => 'France',
            'Ville' => 'Marseille',
            'Salle' => $salleJson,
            'Nom aéroport 1' => 'Marseille Provence',
            'Nom de la gare 1' => 'Gare Saint-Charles',
            'Salles de séminaires' => 'Grande salle modulable.',
            'Photos' => '{"master":["x/master/1.jpg"]}',
        ]));

        self::assertSame(7100, $mapped->syspadId);
        self::assertTrue($mapped->publish);
        self::assertSame('Restaurant', $mapped->gamme);
        self::assertContains('thematique_non_mappee', $mapped->warnings); // Oenotourisme
        self::assertContains('desc_salles_non_mappee', $mapped->warnings);

        $restaurant = $mapped->restaurant;
        self::assertSame(7100, $restaurant->fiche()->code());
        self::assertSame(['GASTRONOMIQUE', 'BORD_DE_MER'], $restaurant->typesRestaurant());
        self::assertSame(['ESAT'], $restaurant->engagementsRse());
        self::assertSame("Vue sur le port.\n\nCuisine de la mer.", $restaurant->descriptionGenerale());
        self::assertSame(['Terrasse', 'Produits frais'], $restaurant->atouts());
        self::assertSame(80, $restaurant->capaciteCocktail());
        self::assertSame(['WIFI'], $restaurant->equipements());
        self::assertTrue($restaurant->accesPmr());
        self::assertSame('FR', $restaurant->localisation()?->countryCode());

        $salles = $restaurant->salles();
        self::assertCount(1, $salles);
        self::assertSame('Salon privé', $salles->first()->nom());
        self::assertSame(30, $salles->first()->capaciteBanquet());

        $types = array_map(static fn ($acces) => $acces->type(), $restaurant->acces()->toArray());
        self::assertContains(TypeAccesRestaurant::Aeroport, $types);
        self::assertContains(TypeAccesRestaurant::Gare, $types);
    }

    public function testDescriptionFallsBackToRestaurationText(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '7200',
            'Nom Français' => 'Bistrot simple',
            'Gamme' => 'Restaurant',
            'Restauration / Gastronomie' => 'Cuisine du marché.',
        ]));
        self::assertSame('Cuisine du marché.', $mapped->restaurant->descriptionGenerale());
        self::assertFalse($mapped->publish);
    }

    public function testSupportsOnlyRestaurants(): void
    {
        self::assertTrue($this->mapper->supports($this->row(['Gamme' => 'Restaurant'])));
        self::assertFalse($this->mapper->supports($this->row(['Gamme' => 'Hôtel'])));
    }

    /** @param array<string, string> $cells */
    private function row(array $cells): RawCsvRow
    {
        return new RawCsvRow(1, $cells);
    }
}
