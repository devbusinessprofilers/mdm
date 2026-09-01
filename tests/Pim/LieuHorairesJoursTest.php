<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\HorairesJours;
use App\Pim\Entity\Lieu\Lieu;
use PHPUnit\Framework\TestCase;

/**
 * Horaires par jour : nettoyage de la saisie (source de vérité unique),
 * amplitude dérivée pour les contrats sortants, écrasement jour par jour
 * (import en masse).
 */
final class LieuHorairesJoursTest extends TestCase
{
    public function testLaSaisieEstNettoyee(): void
    {
        $lieu = new Lieu();
        $lieu->changeDispoHorairesJours([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '08:30', 'fermeture' => '18:00'],
            'DISPO_JOUR_OUVERTURE_2' => ['ouverture' => '7:45', 'fermeture' => '19:15'],
            'DISPO_JOUR_OUVERTURE_3' => ['ouverture' => '  ', 'fermeture' => null],
        ]);

        // « 7:45 » est zéro-paddé pour que min/max lexicographique reste juste.
        self::assertSame([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '08:30', 'fermeture' => '18:00'],
            'DISPO_JOUR_OUVERTURE_2' => ['ouverture' => '07:45', 'fermeture' => '19:15'],
        ], $lieu->dispoHorairesJours());
    }

    public function testUneSaisieVideRendNull(): void
    {
        $lieu = new Lieu();
        $lieu->changeDispoHorairesJours(['DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '09:00', 'fermeture' => '18:00']]);
        $lieu->changeDispoHorairesJours([]);

        self::assertNull($lieu->dispoHorairesJours());
    }

    public function testLAmplitudeEstDerivee(): void
    {
        $amplitude = HorairesJours::amplitude([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '08:30', 'fermeture' => '18:00'],
            'DISPO_JOUR_OUVERTURE_2' => ['ouverture' => '7:45', 'fermeture' => '19:15'],
            'DISPO_JOUR_OUVERTURE_3' => ['ouverture' => null, 'fermeture' => null],
        ]);

        self::assertSame(['ouverture' => '07:45', 'fermeture' => '19:15'], $amplitude);
        self::assertSame(['ouverture' => null, 'fermeture' => null], HorairesJours::amplitude(null));
    }

    public function testLEcrasementJourParJourFusionneEtVide(): void
    {
        $lieu = new Lieu();
        $lieu->changeDispoHorairesJours(['DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '09:00', 'fermeture' => '18:00']]);

        $lieu->changeHoraireJour(['jour' => 'DISPO_JOUR_OUVERTURE_2', 'heures' => ['ouverture' => '10:00', 'fermeture' => '20:00']]);
        self::assertSame([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '09:00', 'fermeture' => '18:00'],
            'DISPO_JOUR_OUVERTURE_2' => ['ouverture' => '10:00', 'fermeture' => '20:00'],
        ], $lieu->horairesJours());

        $lieu->changeHoraireJour(['jour' => 'DISPO_JOUR_OUVERTURE_1', 'heures' => null]);
        $lieu->changeHoraireJour(['jour' => 'DISPO_JOUR_OUVERTURE_2', 'heures' => null]);
        self::assertNull($lieu->horairesJours());
    }
}
