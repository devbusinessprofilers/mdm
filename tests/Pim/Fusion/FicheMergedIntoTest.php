<?php

declare(strict_types=1);

namespace App\Tests\Pim\Fusion;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FicheMergedIntoTest extends TestCase
{
    public function testMarkMergedIntoArchiveAvecLaTraceDeLaSurvivante(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $survivantId = new Ulid();

        $fiche->markMergedInto($survivantId, 'acteur');

        self::assertSame(StatutFiche::Archivee, $fiche->status());
        self::assertNotNull($fiche->archivedAt());
        self::assertTrue($survivantId->equals($fiche->mergedIntoId()));
    }

    public function testMarkMergedIntoRefuseSaPropreFicheEtUneFicheDejaArchivee(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);

        try {
            $fiche->markMergedInto($fiche->id(), 'acteur');
            self::fail('Une fiche ne doit pas se fusionner dans elle-même.');
        } catch (\DomainException) {
        }

        $fiche->archive('acteur');
        $this->expectException(\DomainException::class);
        $fiche->markMergedInto(new Ulid(), 'acteur');
    }

    public function testLeDesarchivageEtLaRepublicationEffacentLaTrace(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->markMergedInto(new Ulid(), 'acteur');
        $fiche->unarchive('acteur');
        self::assertNull($fiche->mergedIntoId());
        self::assertSame(StatutFiche::EnCours, $fiche->status());

        $fiche->markMergedInto(new Ulid(), 'acteur');
        $fiche->republish('acteur');
        self::assertNull($fiche->mergedIntoId());

        $fiche->markMergedInto(new Ulid(), 'acteur');
        // Toute modification métier redescend la fiche en cours : la mention
        // « Fusionnée » n'a plus de sens sur une fiche reprise.
        $fiche->changeLabel('Reprise');
        self::assertNull($fiche->mergedIntoId());
        self::assertSame(StatutFiche::EnCours, $fiche->status());
    }
}
