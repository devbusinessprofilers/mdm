<?php

declare(strict_types=1);

namespace App\Pim\Message;

/**
 * Vérification BAN de l'adresse d'une fiche, déclenchée quand l'empreinte
 * d'adresse diffère de celle du dernier passage (IndexFicheHandler).
 */
final readonly class VerifierAdresseFiche
{
    public function __construct(public string $ficheId)
    {
    }
}
