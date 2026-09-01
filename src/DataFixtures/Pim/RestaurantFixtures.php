<?php

declare(strict_types=1);

namespace App\DataFixtures\Pim;

use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Enum\TypeAccesRestaurant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

final class RestaurantFixtures extends Fixture implements FixtureGroupInterface
{
    private const DEFAULT_COUNT = 100;
    private const BATCH_SIZE = 250;

    /** @var list<array{type: string, cuisine: string, label: string}> */
    private const DEFINITIONS = [
        ['type' => 'BRASSERIE_BISTRO', 'cuisine' => 'FRANCAIS', 'label' => 'Brasserie des Halles'],
        ['type' => 'GASTRONOMIQUE', 'cuisine' => 'CONTEMPORAINE', 'label' => 'Table Contemporaine'],
        ['type' => 'ROOFTOP', 'cuisine' => 'MEDITERRANEEN', 'label' => 'Rooftop Méditerranéen'],
        ['type' => 'BISTRONOMIQUE', 'cuisine' => 'FUSION', 'label' => 'Atelier Bistronomique'],
        ['type' => 'TRATTORIA', 'cuisine' => 'ITALIEN', 'label' => 'Trattoria Business'],
    ];

    public function __construct(
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
    }

    public static function getGroups(): array
    {
        return ['pim-restaurants', 'pim-demo'];
    }

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(
                'Les fixtures Restaurant nécessitent Doctrine ORM.',
            );
        }

        $count = PimFixtureTools::count(
            'PIM_RESTAURANT_FIXTURE_COUNT',
            self::DEFAULT_COUNT,
        );
        $firstCode = PimFixtureTools::nextCode($manager);

        for ($offset = 0; $offset < $count; ++$offset) {
            $code = $firstCode + $offset;
            $definition = self::DEFINITIONS[
                $offset % count(self::DEFINITIONS)
            ];
            $label = sprintf('%s %05d', $definition['label'], $code);

            $restaurant = new Restaurant();
            $restaurant->changeLabel($label);
            $restaurant->changeTypesRestaurant([$definition['type']]);
            $restaurant->changeTypesCuisine([$definition['cuisine']]);
            $restaurant->changeSpecificitesAlimentaires(['PRODUITS_LOCAUX']);
            $restaurant->changeTypesEvenement(['DEJEUNER_ASSIS']);
            $restaurant->changeSiteOfficiel('https://example.test/restaurant');
            $restaurant->changePrivatisationTotale(0 === $offset % 3);
            $restaurant->changePrivatisationPartielle(true);
            $restaurant->changeJoursOuverture([
                'LUNDI',
                'MARDI',
                'MERCREDI',
                'JEUDI',
                'VENDREDI',
            ]);
            $restaurant->changeHorairesJours(array_fill_keys(
                ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI'],
                ['ouverture' => '12:00', 'fermeture' => '23:00'],
            ));
            $restaurant->changeLocalisation($this->location($offset));
            $restaurant->changeAccesPmr(true);
            $restaurant->changeToilettesPmr(true);
            $restaurant->changeDescriptionGenerale(
                'Restaurant de démonstration généré automatiquement.',
            );
            $restaurant->changeAtouts([
                'Cuisine de saison',
                'Accueil des groupes',
                'Salle privatisable',
                'Accès central',
                'Équipe événementielle',
            ]);
            $restaurant->changeCapaciteAssiseMax(120);
            $restaurant->changeCapaciteEspacePrivatisable(60);
            $restaurant->changeCapaciteBanquet(100);
            $restaurant->changeCapaciteCocktail(180);
            $restaurant->changeServices([
                'MENUS_PERSONNALISABLES',
                'CAFE_ACCUEIL',
            ]);
            $restaurant->changeEquipements(['WIFI', 'CLIMATISATION']);
            $restaurant->changeEngagementsRse([
                'CIRCUITS_COURTS',
                'ANTI_GASPILLAGE',
            ]);
            $restaurant->changeYoutubeUrl(
                'https://www.youtube.com/watch?v=restaurant-demo',
            );
            $restaurant->addAcces(
                $this->access(TypeAccesRestaurant::Gare, 'Gare centrale', 0),
            );
            $restaurant->addAcces(
                $this->access(TypeAccesRestaurant::Aeroport, 'Aéroport', 1),
            );
            $restaurant->addSalle($this->room());

            $manager->persist($restaurant);
            $manager->persist(new FicheSearchDocument(
                $restaurant->fiche(),
                implode(' ', [
                    $label,
                    $definition['type'],
                    $definition['cuisine'],
                    'Paris 75008',
                ]),
                $restaurant->fiche()->version(),
            ));

            if (0 === ($offset + 1) % self::BATCH_SIZE) {
                $this->flushAndClear($manager);
            }
        }

        $this->flushAndClear($manager);
    }

    private function location(int $offset): Localisation
    {
        $location = new Localisation();
        $location->changePays('France');
        $location->changeCountryCode('FR');
        $location->changeRegion('Île-de-France');
        $location->changeDepartement('Paris');
        $location->changeRuePostale(
            sprintf('%d rue des Restaurants', $offset + 1),
        );
        $location->changeCodePostal('75008');
        $location->changeVille('Paris');
        $location->changeArrondissement('8e');
        $location->changeLatitude('48.873800000000000');
        $location->changeLongitude('2.295000000000000');

        return $location;
    }

    private function access(
        TypeAccesRestaurant $type,
        string $name,
        int $position,
    ): RestaurantAcces {
        $access = new RestaurantAcces();
        $access->changeType($type);
        $access->changeNom($name);
        $access->changePosition($position);

        return $access;
    }

    private function room(): RestaurantSalle
    {
        $room = new RestaurantSalle();
        $room->changeNom('Salon privatisable');
        $room->changeSuperficie(90);
        $room->changeCapaciteReunion(40);
        $room->changeCapaciteU(30);
        $room->changeCapaciteClasse(50);
        $room->changeCapaciteTheatre(80);
        $room->changeCapaciteCabaret(55);
        $room->changeCapaciteBanquet(60);
        $room->changeCapaciteCocktail(100);
        $room->changeCapaciteAuditorium(80);
        $room->changeLumiereJour(true);
        $room->changeAccesPmr(true);
        $room->changeEspaceDansant(true);
        $room->changeClimatisee(true);

        return $room;
    }

    private function flushAndClear(EntityManagerInterface $manager): void
    {
        $manager->flush();
        $manager->clear();
        $this->debugDataHolder?->reset();
    }
}
