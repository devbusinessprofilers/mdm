<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;

final class FicheWorkflowTest extends TestCase
{
    public function testInternalValidationAndRejectionRequireTheExpectedState(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->submitForValidation('editor');
        self::assertSame(StatutFiche::EnAttenteValidation, $fiche->status());

        $fiche->rejectValidation('validator', 'Adresse à corriger');
        self::assertSame(StatutFiche::EnCours, $fiche->status());
        self::assertSame('Adresse à corriger', $fiche->validationFeedback());

        $fiche->submitForValidation('editor');
        $fiche->validate('validator');
        self::assertSame(StatutFiche::Validee, $fiche->status());
        self::assertNull($fiche->publishedAt());

        $fiche->publish();
        self::assertSame(StatutFiche::Publiee, $fiche->status());
        self::assertNotNull($fiche->publishedAt());

        $fiche->archive('validator');
        self::assertSame(StatutFiche::Archivee, $fiche->status());
    }

    public function testInternalEditResetsPublishedFicheButTechnicalUpdateDoesNot(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->publishForImport();
        $fiche->markSystemChanged();
        self::assertSame(StatutFiche::Publiee, $fiche->status());

        $fiche->changeLabel('Nouveau libellé');
        self::assertSame(StatutFiche::EnCours, $fiche->status());
    }

    public function testExternalUpdatePublishesDirectly(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->submitForValidation('editor');
        $fiche->publishForImport();
        self::assertSame(StatutFiche::Publiee, $fiche->status());
        self::assertNull($fiche->validationFeedback());
    }
}
