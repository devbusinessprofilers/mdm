<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Dashboard\Journal\EtatTraitement;
use PHPUnit\Framework\TestCase;

/** Les vocabulaires des tables de suivi convergent vers les états du journal. */
final class EtatTraitementTest extends TestCase
{
    public function testLesCinqMotsPourEchecDonnentUnSeulEtat(): void
    {
        foreach (['echoue', 'failed', 'en_erreur'] as $statut) {
            self::assertSame(EtatTraitement::Echoue, EtatTraitement::depuisStatut($statut), $statut);
            self::assertTrue(EtatTraitement::depuisStatut($statut)->estEchec());
        }
        self::assertSame(EtatTraitement::TermineAvecErreurs, EtatTraitement::depuisStatut('termine_avec_erreurs'));
        self::assertTrue(EtatTraitement::TermineAvecErreurs->estEchec());
    }

    public function testLesMotsPourTermineEtEnFileConvergent(): void
    {
        foreach (['termine', 'terminee', 'ready', 'reviewed', 'disponible', 'processed', 'synced', 'published'] as $statut) {
            self::assertSame(EtatTraitement::Termine, EtatTraitement::depuisStatut($statut), $statut);
        }
        foreach (['en_attente', 'pending', 'uploaded', 'queued'] as $statut) {
            self::assertSame(EtatTraitement::EnFile, EtatTraitement::depuisStatut($statut), $statut);
        }
        self::assertSame(EtatTraitement::Retire, EtatTraitement::depuisStatut('removed'));
        self::assertSame(EtatTraitement::Expire, EtatTraitement::depuisStatut('expiree'));
        self::assertFalse(EtatTraitement::Termine->estEchec());
    }

    public function testUnStatutInconnuResteLisibleSansEtreUnEchec(): void
    {
        $etat = EtatTraitement::depuisStatut('quelque_chose');

        self::assertSame(EtatTraitement::Inconnu, $etat);
        self::assertSame('Inconnu', $etat->libelle());
        self::assertSame('bg-neutral-200', $etat->teinte());
        self::assertFalse($etat->estEchec());
    }
}
