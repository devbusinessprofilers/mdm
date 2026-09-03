<?php

declare(strict_types=1);

namespace App\Dashboard\Twig;

use App\Dashboard\Journal\EtatTraitement;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `etat_traitement('failed')` : l'état du journal (libellé, teinte, échec) pour un statut brut de table de suivi. */
final class EtatTraitementExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('etat_traitement', EtatTraitement::depuisStatut(...)),
        ];
    }
}
