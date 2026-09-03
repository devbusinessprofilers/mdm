<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeAccesLieu;

/**
 * Remplit sur un Lieu tous les champs « Obligatoire » de la bible VERSION BP
 * (LieuObligationsPublication) : les tests qui soumettent ou publient une
 * fiche Lieu passent par ici, les photos restant à leur charge.
 */
final class LieuComplet
{
    public static function completer(Lieu $lieu): Lieu
    {
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_20']);
        $lieu->changeDescGenerale('Description complète du lieu pour la soumission.');
        foreach ([[TypeAccesLieu::Aeroport, 'Aéroport de test'], [TypeAccesLieu::Gare, 'Gare de test']] as [$type, $nom]) {
            $acces = new AccesLieu();
            $acces->changeType($type);
            $acces->changeNom($nom);
            $lieu->addAcces($acces);
        }
        $lieu->changeChambreNbTotal(10);
        $lieu->changeChambreCapaciteTotale(12);
        $lieu->changeChambreDescGenerale('Chambres confortables.');
        $lieu->changeSalleReunionNbTotal(2);
        $lieu->changeSalleReunionCapaciteMaxCocktail(100);
        $lieu->changeSalleReunionCapaciteMaxTheatre(80);
        $lieu->changeSalleReunionCapaciteMinTheatre(20);
        $lieu->changeSalleReunionSurfaceMinReunion(30);
        $lieu->changeSalleReunionSurfaceMaxReunion(120);
        $lieu->changeSalleReunionDescSalleSeminaire('Salles lumineuses.');
        $lieu->changeRestaurantTotal(1);
        $lieu->changeRestaurantCapaciteAssis(60);

        return $lieu;
    }
}
