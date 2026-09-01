<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\HorairesOsm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HorairesOsmTest extends TestCase
{
    public function testUnePlageSemaineSimple(): void
    {
        $resultat = HorairesOsm::parser('Mo-Fr 09:00-18:00');

        self::assertNotNull($resultat);
        self::assertSame(
            ['DISPO_JOUR_OUVERTURE_1', 'DISPO_JOUR_OUVERTURE_2', 'DISPO_JOUR_OUVERTURE_3', 'DISPO_JOUR_OUVERTURE_4', 'DISPO_JOUR_OUVERTURE_5'],
            $resultat['jours'],
        );
        self::assertSame(['ouverture' => '09:00', 'fermeture' => '18:00'], $resultat['horaires']['DISPO_JOUR_OUVERTURE_3']);
    }

    public function testListeDeJoursEtPlusieursRegles(): void
    {
        $resultat = HorairesOsm::parser('Mo,We,Fr 10:00-19:00; Sa 9:30-17:00');

        self::assertNotNull($resultat);
        self::assertSame(
            ['DISPO_JOUR_OUVERTURE_1', 'DISPO_JOUR_OUVERTURE_3', 'DISPO_JOUR_OUVERTURE_5', 'DISPO_JOUR_OUVERTURE_6'],
            $resultat['jours'],
        );
        // 9:30 normalisé en 09:30.
        self::assertSame(['ouverture' => '09:30', 'fermeture' => '17:00'], $resultat['horaires']['DISPO_JOUR_OUVERTURE_6']);
    }

    public function testVingtQuatreSeptDonneLesJoursSansHoraires(): void
    {
        $resultat = HorairesOsm::parser('24/7');

        self::assertNotNull($resultat);
        self::assertCount(7, $resultat['jours']);
        self::assertSame([], $resultat['horaires']);
    }

    /** Tout motif ambigu est rejeté en bloc : mieux vaut rien qu'une suggestion fausse. */
    #[DataProvider('motifsRejetes')]
    public function testLesMotifsComplexesSontRejetes(string $tag): void
    {
        self::assertNull(HorairesOsm::parser($tag));
    }

    /** @return iterable<string, array{string}> */
    public static function motifsRejetes(): iterable
    {
        yield 'mois' => ['Jul-Aug Mo-Fr 09:00-18:00'];
        yield 'jours fériés' => ['Mo-Fr 09:00-18:00; PH off'];
        yield 'fermeture explicite' => ['Mo-Fr 09:00-18:00; Su closed'];
        yield 'plages multiples' => ['Mo-Fr 09:00-12:00,14:00-18:00'];
        yield 'passe minuit' => ['Fr-Sa 22:00-02:00'];
        yield 'plage de jours inversée' => ['Su-Mo 09:00-18:00'];
        yield 'jour défini deux fois' => ['Mo-Fr 09:00-18:00; Fr 10:00-16:00'];
        yield 'heure invalide' => ['Mo 09:00-25:00'];
        yield 'vide' => [''];
        yield 'texte libre' => ['sur rendez-vous'];
    }

    public function testLeResumeRegroupeLesJoursConsecutifsIdentiques(): void
    {
        $resultat = HorairesOsm::parser('Mo-Fr 09:00-18:00; Sa 10:00-17:00');

        self::assertNotNull($resultat);
        self::assertSame('Lun-Ven 09:00-18:00, Sam 10:00-17:00', HorairesOsm::resume($resultat['horaires']));
    }
}
