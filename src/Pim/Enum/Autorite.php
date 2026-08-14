<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/**
 * Autorité d'un champ de fiche : qui fait foi sur sa valeur. Rendue en pastille
 * à côté du libellé du champ dans l'éditeur (chrome « autorité »).
 *
 * Pour cette version, seul Salesforce pilote réellement des champs (le statut
 * partenaire, écrasé à chaque refresh) ; tout le reste relève du MDM.
 * Prestataire complète le vocabulaire pour un arbitrage ultérieur.
 *
 * Les classes Tailwind correspondantes sont portées par le thème de formulaire
 * de la fiche (templates/pim/form/_form-theme-fiche.html.twig) — Tailwind ne
 * scanne pas le PHP, elles doivent y figurer en toutes lettres.
 */
enum Autorite: string
{
    case Mdm = 'MDM';
    case Salesforce = 'Salesforce';
    case Prestataire = 'Prestataire';
}
