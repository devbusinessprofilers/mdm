<?php

declare(strict_types=1);

namespace App\DataFixtures\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\ModeTarificationActivite;
use App\Pim\Enum\TypeOffreActivite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

final class ActiviteFixtures extends Fixture implements FixtureGroupInterface
{
    private const DEFAULT_COUNT = 100;
    private const BATCH_SIZE = 250;

    /** @var list<string> */
    private const NAMES = [
        'Escape game collaboratif',
        'Atelier cuisine',
        'Olympiades responsables',
        'Fresque créative',
        'Challenge digital',
    ];

    public function __construct(
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
    }

    public static function getGroups(): array
    {
        return ['pim-activites', 'pim-demo'];
    }

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException('Les fixtures Activité nécessitent Doctrine ORM.');
        }

        $count = PimFixtureTools::count(
            'PIM_ACTIVITE_FIXTURE_COUNT',
            self::DEFAULT_COUNT,
        );
        $firstCode = PimFixtureTools::nextCode($manager);

        for ($offset = 0; $offset < $count; ++$offset) {
            $code = $firstCode + $offset;
            $name = self::NAMES[$offset % count(self::NAMES)];
            $label = sprintf('%s %05d', $name, $code);
            $mobile = 0 === $offset % 2;

            $activity = new Activite();
            $activity->changeLabel($label);
            $activity->changeTypes([
                0 === $offset % 3 ? 'TYPE_EXT_INT_1' : 'TYPE_EXT_INT_2',
            ]);
            $activity->changeThematiques(['TA_SPORTIVE_LUDIQUE']);
            $activity->changeSousThematiques(['TA_SPORTIVE_LUDIQUE_SS_1']);
            $activity->changeLangues(['LANGUE_PARLEE_1', 'LANGUE_PARLEE_2']);
            $activity->changeObjectifs(['OBJECTIF_SEMINAIRE_1']);
            $activity->changeDescriptionGenerale(
                'Activité de démonstration générée automatiquement.',
            );
            $activity->changeComprendPrestation(
                'Animation, matériel et encadrement.',
            );
            $activity->changeParticipantsMin(4);
            $activity->changeParticipantsMax(80);
            $activity->changeDureeMinMinutes(60);
            $activity->changeDureeMaxMinutes(180);
            $activity->changePlus(['Clé en main', 'Animateur dédié']);
            $activity->changeTarifParPersonne('45.00');
            $activity->changeYoutubeUrl('https://www.youtube.com/watch?v=demo');
            $activity->changeModeIntervention(
                $mobile
                    ? ModeInterventionActivite::Mobile
                    : ModeInterventionActivite::Fixe,
            );

            if ($mobile) {
                $activity->changeTouteFrance(true);
                $activity->changePaysMobiles(['France']);
                $activity->changeRegionsMobiles(['France entière']);
                $activity->changeDepartementsMobiles(['Tous départements']);
                $locationText = 'France entière';
            } else {
                $location = $this->parisLocation($offset);
                $activity->changeLocalisation($location);
                $locationText = 'Paris 75008';
            }

            $offer = new OffreActivite();
            $offer->changeType(TypeOffreActivite::Forfait);
            $offer->changeNom('Forfait découverte');
            $offer->changeParticipantsMin(4);
            $offer->changeParticipantsMax(20);
            $offer->changePrix('850.00');
            $offer->changeModeTarification(ModeTarificationActivite::Forfait);
            $activity->addOffre($offer);

            if (0 !== $offset % 5) {
                $activity->fiche()->publishForImport();
            }

            $manager->persist($activity);
            $manager->persist(new FicheSearchDocument(
                $activity->fiche(),
                implode(' ', [$label, $name, $locationText]),
                $activity->fiche()->version(),
            ));

            if (0 === ($offset + 1) % self::BATCH_SIZE) {
                $this->flushAndClear($manager);
            }
        }

        $this->flushAndClear($manager);
    }

    private function parisLocation(int $offset): Localisation
    {
        $location = new Localisation();
        $location->changePays('France');
        $location->changeCountryCode('FR');
        $location->changeRegion('Île-de-France');
        $location->changeDepartement('Paris');
        $location->changeRuePostale(sprintf('%d avenue des Activités', $offset + 1));
        $location->changeCodePostal('75008');
        $location->changeVille('Paris');
        $location->changeLatitude('48.873800000000000');
        $location->changeLongitude('2.295000000000000');

        return $location;
    }

    private function flushAndClear(EntityManagerInterface $manager): void
    {
        $manager->flush();
        $manager->clear();
        $this->debugDataHolder?->reset();
    }
}
