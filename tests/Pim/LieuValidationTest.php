<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LieuValidationTest extends KernelTestCase
{
    public function testIncompleteDraftCanBeSavedButCannotBeSubmitted(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $lieu = new Lieu();
        self::assertCount(
            0,
            $validator->validate($lieu, null, [ValidationGroups::DRAFT]),
        );
        $submission = $validator->validate($lieu, null, [
            ValidationGroups::DRAFT,
            ValidationGroups::SUBMISSION,
        ]);
        self::assertGreaterThanOrEqual(2, count($submission));
        self::assertStringContainsString(
            'nom du lieu est obligatoire',
            (string) $submission,
        );
        self::assertStringContainsString(
            'entre 4 et 25 photos',
            (string) $submission,
        );
    }

    public function testDraftBoundariesAndCrossFieldRulesAreCentralized(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel(str_repeat('a', 69));
        $lieu->changeAtout1(str_repeat('b', 35));
        $lieu->changeDescGenerale(str_repeat('c', 1000));
        $lieu->changeDispoHorairesJours(['DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '09:00', 'fermeture' => '18:00']]);
        $salle = new Salle();
        $salle->changeNom('Plénière');
        $salle->changeCapaciteTheatre(999999);
        $lieu->addSalle($salle);
        self::assertCount(
            0,
            $validator->validate($lieu, null, [ValidationGroups::DRAFT]),
        );
        $lieu->changeLabel(str_repeat('a', 70).',');
        $lieu->changeAtout1(str_repeat('b', 36));
        $lieu->changeDescGenerale(str_repeat('c', 1001));
        $lieu->changeDispoHorairesJours(['DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '09:00', 'fermeture' => '08:00']]);
        $salle->changeCapaciteTheatre(1000000);
        $period = new PeriodeFermeture();
        $period->changeNom('Inversée');
        $period->changeDateDebut(new \DateTimeImmutable('2026-08-02'));
        $period->changeDateFin(new \DateTimeImmutable('2026-08-01'));
        $lieu->addPeriodeFermeture($period);
        $violations = (string) $validator->validate($lieu, null, [
            ValidationGroups::DRAFT,
        ]);
        foreach (
            [
                '69 caractères',
                'virgule',
                '35 caractères',
                '1 000 caractères',
                'fermeture doit être postérieure',
                '999 999',
                'fin de la période',
            ] as $message
        ) {
            self::assertStringContainsString($message, $violations);
        }
    }
}
