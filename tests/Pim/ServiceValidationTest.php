<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ServiceValidationTest extends KernelTestCase
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
        $service = new ServiceEvenementiel();

        self::assertCount(
            0,
            $this->validator->validate($service, null, [
                ValidationGroups::DRAFT,
            ]),
        );

        $violations = $this->validator->validate($service, null, [
            ValidationGroups::SUBMISSION,
        ]);
        $paths = array_map(
            static fn(
                ConstraintViolationInterface $violation,
            ): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        );

        self::assertGreaterThan(10, count($violations));
        self::assertContains("prestations", $paths);
        self::assertContains("ressources", $paths);
        self::assertContains("tarifParHeure", $paths);
    }

    public function testNegativeTariffsAreRejectedInDraft(): void
    {
        $service = new ServiceEvenementiel();
        $service->changeTarifParHeure(-0.01);

        $violations = $this->validator->validate($service, null, [
            ValidationGroups::DRAFT,
        ]);

        self::assertSame(
            "tarifParHeure",
            $violations->get(0)->getPropertyPath(),
        );
    }

    public function testMobileServiceRequiresEveryTextualAreaAtSubmission(): void
    {
        $service = new ServiceEvenementiel();
        $service->changeModeIntervention(ModeInterventionService::Mobile);
        $service->changePaysMobiles(["France"]);

        $violations = $this->validator->validate($service, null, [
            ValidationGroups::SUBMISSION,
        ]);
        $paths = array_map(
            static fn(
                ConstraintViolationInterface $violation,
            ): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        );

        self::assertContains("regionsMobiles", $paths);
        self::assertContains("departementsMobiles", $paths);
    }
}
