<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use PHPUnit\Framework\TestCase;

/**
 * Horaires par jour : nettoyage de la saisie, dérivation de l'amplitude
 * globale (contrat marketplace) et repli du getter sur l'horaire global
 * historique décliné sur les jours d'ouverture.
 */
final class LieuHorairesJoursTest extends TestCase
{
    public function testLaSaisieEstNettoyeeEtDeriveLAmplitudeGlobale(): void
    {
        $lieu = new Lieu();
        $lieu->changeDispoHorairesJours([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '08:30', 'fermeture' => '18:00'],
            'DISPO_JOUR_OUVERTURE_2' => ['ouverture' => '07:45', 'fermeture' => '19:15'],
            'DISPO_JOUR_OUVERTURE_3' => ['ouverture' => '  ', 'fermeture' => null],
        ]);

        self::assertSame([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '08:30', 'fermeture' => '18:00'],
            'DISPO_JOUR_OUVERTURE_2' => ['ouverture' => '07:45', 'fermeture' => '19:15'],
        ], $lieu->dispoHorairesJours());
        self::assertSame(7, $lieu->dispoHeureOuvertureHeure());
        self::assertSame(45, $lieu->dispoHeureOuvertureMinutes());
        self::assertSame(19, $lieu->dispoHeureFermetureHeure());
        self::assertSame(15, $lieu->dispoHeureFermetureMinutes());
    }

    public function testUneSaisieVideConserveLesHorairesGlobauxHistoriques(): void
    {
        $lieu = new Lieu();
        $lieu->changeDispoHeureOuvertureHeure(9);
        $lieu->changeDispoHeureFermetureHeure(17);
        $lieu->changeDispoHorairesJours([]);

        self::assertSame(9, $lieu->dispoHeureOuvertureHeure());
        self::assertSame(17, $lieu->dispoHeureFermetureHeure());
    }

    public function testLeGetterSeReplieSurLHoraireGlobalDeclineSurLesJoursOuverts(): void
    {
        $lieu = new Lieu();
        $lieu->changeJoursOuverture(['DISPO_JOUR_OUVERTURE_1', 'DISPO_JOUR_OUVERTURE_5']);
        $lieu->changeDispoHeureOuvertureHeure(9);
        $lieu->changeDispoHeureOuvertureMinutes(30);
        $lieu->changeDispoHeureFermetureHeure(17);

        self::assertSame([
            'DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '09:30', 'fermeture' => '17:00'],
            'DISPO_JOUR_OUVERTURE_5' => ['ouverture' => '09:30', 'fermeture' => '17:00'],
        ], $lieu->dispoHorairesJours());
    }
}
