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

    public function testArchivedFicheCanReturnToOtherStatuses(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->publishForImport();
        $fiche->archive('validator');
        self::assertSame(StatutFiche::Archivee, $fiche->status());

        // Désarchiver : retour en cours, la date d'archivage est effacée.
        $fiche->unarchive('validator');
        self::assertSame(StatutFiche::EnCours, $fiche->status());
        self::assertNull($fiche->archivedAt());

        // Republier : retour direct en publiée depuis « archivée ».
        $fiche->archive('validator');
        $fiche->republish('validator');
        self::assertSame(StatutFiche::Publiee, $fiche->status());
        self::assertNull($fiche->archivedAt());
        self::assertNotNull($fiche->publishedAt());
    }

    public function testUnarchiveAndRepublishRequireAnArchivedFiche(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->publishForImport();

        $this->expectException(\DomainException::class);
        $fiche->unarchive('validator');
    }

    public function testArchiveWorksFromAnyStatusButIsIdempotent(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        // Depuis « en cours » directement, sans passer par « publiée ».
        $fiche->archive('validator');
        self::assertSame(StatutFiche::Archivee, $fiche->status());

        $this->expectException(\DomainException::class);
        $fiche->archive('validator');
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

    public function testUnpublishForMissingRequiredFieldsDemotesAndExplains(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->publishForImport();

        $fiche->unpublishForMissingRequiredFields(['Typologie', 'Texte de description']);

        self::assertSame(StatutFiche::EnCours, $fiche->status());
        self::assertSame('Dépublication : champs obligatoires vidés — Typologie, Texte de description.', $fiche->validationFeedback());

        $this->expectException(\DomainException::class);
        $fiche->unpublishForMissingRequiredFields(['Typologie']);
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
