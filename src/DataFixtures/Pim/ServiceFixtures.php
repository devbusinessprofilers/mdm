<?php

declare(strict_types=1);

namespace App\DataFixtures\Pim;

use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

final class ServiceFixtures extends Fixture implements FixtureGroupInterface
{
    private const DEFAULT_COUNT = 100;
    private const BATCH_SIZE = 250;

    /** @var list<array{code: string, label: string}> */
    private const SERVICES = [
        ['code' => 'TS_TECHNIQUE_AUDIOVISUEL', 'label' => 'Technique audiovisuelle'],
        ['code' => 'TS_ANIMATION_ARTISTE', 'label' => 'Animations et artistes'],
        ['code' => 'TS_TRANSPORT_LOGISTIQUE', 'label' => 'Transport et logistique'],
        ['code' => 'TS_DIGITAL_HYBRIDE', 'label' => 'Solutions digitales hybrides'],
        ['code' => 'TS_CADEAU_CLIENT_GOODIE', 'label' => 'Cadeaux et goodies'],
    ];

    public function __construct(
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
    }

    public static function getGroups(): array
    {
        return ['pim-services', 'pim-demo'];
    }

    public function load(ObjectManager $manager): void
    {
        if (!($manager instanceof EntityManagerInterface)) {
            throw new \LogicException('Les fixtures Service nécessitent Doctrine ORM.');
        }

        $count = PimFixtureTools::count(
            'PIM_SERVICE_FIXTURE_COUNT',
            self::DEFAULT_COUNT,
        );
        $firstCode = PimFixtureTools::nextCode($manager);

        for ($offset = 0; $offset < $count; ++$offset) {
            $code = $firstCode + $offset;
            $definition = self::SERVICES[$offset % count(self::SERVICES)];
            $label = sprintf('%s %05d', $definition['label'], $code);
            $mobile = 0 === $offset % 2;

            $service = new ServiceEvenementiel();
            $service->changeLabel($label);
            $service->changePrestations([$definition['code']]);
            $service->changeDescriptionGenerale(
                'Service événementiel de démonstration généré automatiquement.',
            );
            $service->changePrestataireEsat(0 === $offset % 4);
            $service->changeDemarcheRse(true);
            $service->changeAdapteFemmesEnceintes(true);
            $service->changeAdapteMalentendants(0 === $offset % 3);
            $service->changeAdapteMalvoyants(0 === $offset % 3);
            $service->changeMaterielInclus(true);
            $service->changeEquipementParticipantsRequis(false);
            $service->changeEquipementReceptionRequis(0 === $offset % 2);
            $service->changeContraintesLogistiques(0 === $offset % 2);
            $service->changeTarifParPrestation(750.0 + $offset);
            $service->changeTarifParPersonne(35.5);
            $service->changeTarifParJour(1_200.0);
            $service->changeTarifParDemiJournee(700.0);
            $service->changeTarifParHeure(95.0);
            $service->changeSurDevis(false);
            $service->changeYoutubeUrl('https://www.youtube.com/watch?v=demo');
            $service->changeModeIntervention(
                $mobile
                    ? ModeInterventionService::Mobile
                    : ModeInterventionService::Fixe,
            );

            if ($mobile) {
                $service->changePaysMobiles(['France']);
                $service->changeRegionsMobiles(['France entière']);
                $service->changeDepartementsMobiles(['Tous départements']);
                $locationText = 'France entière';
            } else {
                $location = $this->lyonLocation($offset);
                $service->changeLocalisation($location);
                $locationText = 'Lyon 69002';
            }

            if (0 !== $offset % 5) {
                $service->fiche()->publishForImport();
            }

            $manager->persist($service);
            $manager->persist(new FicheSearchDocument(
                $service->fiche(),
                implode(' ', [
                    $label,
                    $definition['label'],
                    $definition['code'],
                    $locationText,
                ]),
                $service->fiche()->version(),
            ));

            if (0 === ($offset + 1) % self::BATCH_SIZE) {
                $this->flushAndClear($manager);
            }
        }

        $this->flushAndClear($manager);
    }

    private function lyonLocation(int $offset): Localisation
    {
        $location = new Localisation();
        $location->changePays('France');
        $location->changeCountryCode('FR');
        $location->changeRegion('Auvergne-Rhône-Alpes');
        $location->changeDepartement('Rhône');
        $location->changeRuePostale(sprintf('%d rue des Services', $offset + 1));
        $location->changeCodePostal('69002');
        $location->changeVille('Lyon');
        $location->changeLatitude('45.748500000000000');
        $location->changeLongitude('4.846700000000000');

        return $location;
    }

    private function flushAndClear(EntityManagerInterface $manager): void
    {
        $manager->flush();
        $manager->clear();
        $this->debugDataHolder?->reset();
    }
}
