<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\TypeOffreActivite;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ActiviteValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;
    }

    public function testIncompleteDraftCanBeSavedButNotSubmitted(): void
    {
        $a = new Activite();
        self::assertCount(
            0,
            $this->validator->validate($a, null, [ValidationGroups::DRAFT]),
        );
        $violations = $this->validator->validate($a, null, [
            ValidationGroups::SUBMISSION,
        ]);
        self::assertGreaterThan(5, count($violations));
        $paths = array_map(
            static fn ($v): string => $v->getPropertyPath(),
            iterator_to_array($violations),
        );
        self::assertContains('prestataire', $paths);
        self::assertContains('ressources', $paths);
    }

    /** Maquette portail : cinq « plus » et cinq objectifs de séminaire au maximum. */
    public function testCinqPlusEtCinqObjectifsAuMaximum(): void
    {
        $a = new Activite();
        $a->changePlus(['1', '2', '3', '4', '5', '6']);
        self::assertSame(['1', '2', '3', '4', '5'], $a->plus());

        $a->changeObjectifs(['OBJECTIF_SEMINAIRE_1', 'OBJECTIF_SEMINAIRE_2', 'OBJECTIF_SEMINAIRE_3', 'OBJECTIF_SEMINAIRE_4', 'OBJECTIF_SEMINAIRE_5', 'OBJECTIF_SEMINAIRE_6']);
        $messages = array_map(
            static fn ($v): string => (string) $v->getMessage(),
            iterator_to_array($this->validator->validate($a, null, [ValidationGroups::DRAFT])),
        );
        self::assertContains('Cinq objectifs de séminaire au maximum.', $messages);
    }

    public function testRangesAmountsAndCollectionLimitsAreCheckedInDraft(): void
    {
        $a = new Activite();
        $a->changeParticipantsMin(10);
        $a->changeParticipantsMax(9);
        $a->changeDureeMinMinutes(60);
        $a->changeDureeMaxMinutes(59);
        $a->changeTarifParPersonne('12.345');
        for ($i = 0; $i < 4; ++$i) {
            $offer = new OffreActivite();
            $offer->changeType(TypeOffreActivite::Forfait);
            $a->addOffre($offer);
        }
        $violations = $this->validator->validate($a, null, [
            ValidationGroups::DRAFT,
        ]);
        $messages = array_map(
            static fn ($v): string => (string) $v->getMessage(),
            iterator_to_array($violations),
        );
        self::assertTrue(
            (bool) array_filter(
                $messages,
                static fn (string $m): bool => str_contains($m, 'participants'),
            ),
        );
        self::assertTrue(
            (bool) array_filter(
                $messages,
                static fn (string $m): bool => str_contains($m, 'durée'),
            ),
        );
        self::assertTrue(
            (bool) array_filter(
                $messages,
                static fn (string $m): bool => str_contains(
                    $m,
                    'deux décimales',
                ),
            ),
        );
        self::assertTrue(
            (bool) array_filter(
                $messages,
                static fn (string $m): bool => str_contains(
                    $m,
                    'trois forfaits',
                ),
            ),
        );
    }

    public function testMobileActivityNeedsARegionAtSubmission(): void
    {
        $a = new Activite();
        $a->changeModeIntervention(ModeInterventionActivite::Mobile);
        $violations = $this->validator->validate($a, null, [
            ValidationGroups::SUBMISSION,
        ]);
        self::assertTrue(
            (bool) array_filter(
                iterator_to_array($violations),
                static fn ($v): bool => 'regionsMobiles' ===
                    $v->getPropertyPath(),
            ),
        );
    }
}
