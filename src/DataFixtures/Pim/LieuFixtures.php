<?php

declare(strict_types=1);

namespace App\DataFixtures\Pim;

use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\TypeFiche;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

final class LieuFixtures extends Fixture implements FixtureGroupInterface
{
    private const DEFAULT_COUNT = 100;
    private const MAX_COUNT = 100_000;
    private const BATCH_SIZE = 250;

    /** @var list<array{ville: string, codePostal: string, region: string, departement: string, latitude: string, longitude: string}> */
    private const CITIES = [
        ['ville' => 'Paris', 'codePostal' => '75008', 'region' => 'Île-de-France', 'departement' => 'Paris', 'latitude' => '48.873800000000000', 'longitude' => '2.295000000000000'],
        ['ville' => 'Lyon', 'codePostal' => '69002', 'region' => 'Auvergne-Rhône-Alpes', 'departement' => 'Rhône', 'latitude' => '45.748500000000000', 'longitude' => '4.846700000000000'],
        ['ville' => 'Marseille', 'codePostal' => '13001', 'region' => 'Provence-Alpes-Côte d’Azur', 'departement' => 'Bouches-du-Rhône', 'latitude' => '43.296500000000000', 'longitude' => '5.369800000000000'],
        ['ville' => 'Bordeaux', 'codePostal' => '33000', 'region' => 'Nouvelle-Aquitaine', 'departement' => 'Gironde', 'latitude' => '44.837800000000000', 'longitude' => '-0.579200000000000'],
        ['ville' => 'Lille', 'codePostal' => '59000', 'region' => 'Hauts-de-France', 'departement' => 'Nord', 'latitude' => '50.629200000000000', 'longitude' => '3.057300000000000'],
        ['ville' => 'Nantes', 'codePostal' => '44000', 'region' => 'Pays de la Loire', 'departement' => 'Loire-Atlantique', 'latitude' => '47.218400000000000', 'longitude' => '-1.553600000000000'],
        ['ville' => 'Toulouse', 'codePostal' => '31000', 'region' => 'Occitanie', 'departement' => 'Haute-Garonne', 'latitude' => '43.604700000000000', 'longitude' => '1.444200000000000'],
        ['ville' => 'Strasbourg', 'codePostal' => '67000', 'region' => 'Grand Est', 'departement' => 'Bas-Rhin', 'latitude' => '48.573400000000000', 'longitude' => '7.752100000000000'],
        ['ville' => 'Rennes', 'codePostal' => '35000', 'region' => 'Bretagne', 'departement' => 'Ille-et-Vilaine', 'latitude' => '48.117300000000000', 'longitude' => '-1.677800000000000'],
        ['ville' => 'Nice', 'codePostal' => '06000', 'region' => 'Provence-Alpes-Côte d’Azur', 'departement' => 'Alpes-Maritimes', 'latitude' => '43.710200000000000', 'longitude' => '7.262000000000000'],
    ];

    /** @var list<string> */
    private const NAMES = ['Grand Hôtel', 'Domaine des Lumières', 'Maison des Congrès', 'Atelier', 'Pavillon', 'Villa', 'Château', 'Loft', 'Rooftop', 'Centre de conférences'];

    /** @var list<string> */
    private const STREETS = ['rue de la République', 'avenue Victor-Hugo', 'boulevard des Arts', 'rue du Parc', 'quai des Lumières', 'place de la Gare'];

    public function __construct(
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
    }

    public static function getGroups(): array
    {
        return ['pim-lieux'];
    }

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException('Les fixtures Lieu nécessitent Doctrine ORM.');
        }

        $count = $this->fixtureCount();
        $randomizer = new Randomizer(new Mt19937(20_260_729));
        $firstCode = $this->nextAvailableCode($manager);

        for ($offset = 0; $offset < $count; ++$offset) {
            $code = $firstCode + $offset;
            $city = self::CITIES[$randomizer->getInt(0, count(self::CITIES) - 1)];
            $name = self::NAMES[$randomizer->getInt(0, count(self::NAMES) - 1)];
            $label = sprintf('%s %s %05d', $name, $city['ville'], $code);

            $lieu = new Lieu();
            $lieu->changeCode($code);
            $lieu->changeLabel($label);
            $lieu->changeGeneraleTypologie([sprintf('GENERALE_TYPOLOGIE_%d', $randomizer->getInt(1, 40))]);
            $lieu->changeGeneraleWebsiteUrl(sprintf('https://lieu-%d.example.test', $code));
            $lieu->changeDescGenerale(sprintf('Lieu de démonstration situé à %s, généré automatiquement pour les tests.', $city['ville']));
            $lieu->changeChambreHebergement(0 === $code % 3);
            $lieu->changeChambreNbTotal(0 === $code % 3 ? $randomizer->getInt(10, 180) : null);
            $lieu->changeSalleReunionExist(true);
            $lieu->changeSalleReunionNbTotal($randomizer->getInt(1, 8));
            $lieu->changePublished(0 !== $code % 5);

            $localisation = new Localisation();
            $localisation->changePays('France');
            $localisation->changeCountryCode('FR');
            $localisation->changeRegion($city['region']);
            $localisation->changeDepartement($city['departement']);
            $localisation->changeRuePostale(sprintf('%d %s', $randomizer->getInt(1, 199), self::STREETS[$randomizer->getInt(0, count(self::STREETS) - 1)]));
            $localisation->changeCodePostal($city['codePostal']);
            $localisation->changeVille($city['ville']);
            $localisation->changeLatitude($city['latitude']);
            $localisation->changeLongitude($city['longitude']);
            $lieu->changeLocalisation($localisation);

            $lieu->administratif()->changeInfoLegaleNom($label.' SAS');
            $lieu->administratif()->changeInfoLegaleSiret(sprintf('%014d', 100_000_000_000_00 + $code));
            $lieu->tarification()->changeSeminaireJourneeJourneeEtude((string) $randomizer->getInt(45, 180));

            if (0 === $offset % 4) {
                $salle = new Salle();
                $salle->changeNom('Salle principale');
                $salle->changeSuperficie($randomizer->getInt(40, 600));
                $salle->changeCapaciteTheatre($randomizer->getInt(20, 500));
                $salle->changeLumiereJour(true);
                $lieu->addSalle($salle);
            }

            $manager->persist($lieu);
            $manager->persist(new FicheSearchDocument(
                $lieu->fiche(),
                implode(' ', [$label, $city['ville'], $city['codePostal'], $city['region'], $city['departement']]),
                $lieu->fiche()->version(),
            ));

            if (0 === ($offset + 1) % self::BATCH_SIZE) {
                $manager->flush();
                $manager->clear();
                $this->resetDoctrineDebugData();
            }
        }

        $manager->flush();
        $manager->clear();
        $this->resetDoctrineDebugData();
    }

    private function fixtureCount(): int
    {
        $configuredCount = getenv('PIM_LIEU_FIXTURE_COUNT');
        if (false === $configuredCount || '' === trim($configuredCount)) {
            return self::DEFAULT_COUNT;
        }

        if (1 !== preg_match('/^\d+$/', $configuredCount)) {
            throw new \InvalidArgumentException('PIM_LIEU_FIXTURE_COUNT doit être un entier positif.');
        }

        $count = (int) $configuredCount;
        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new \InvalidArgumentException(sprintf('PIM_LIEU_FIXTURE_COUNT doit être compris entre 1 et %d.', self::MAX_COUNT));
        }

        return $count;
    }

    private function nextAvailableCode(EntityManagerInterface $manager): int
    {
        $maximum = $manager->getConnection()->fetchOne(
            'SELECT MAX(code) FROM pim_fiche WHERE type = :type',
            ['type' => TypeFiche::Lieu->value],
        );

        return (false === $maximum || null === $maximum ? 0 : (int) $maximum) + 1;
    }

    private function resetDoctrineDebugData(): void
    {
        // En environnement dev, Doctrine conserve chaque requête et sa
        // backtrace pour le profiler. Sur plusieurs dizaines de milliers de
        // lieux, ce journal grossirait même si l'EntityManager est vidé.
        $this->debugDataHolder?->reset();
    }
}
