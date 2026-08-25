<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * L'API externe d'enrichissement est indisponible (réseau, quota, 5xx) : le
 * constat « aucun résultat » n'a pas pu être établi. Les commandes de scan ne
 * doivent alors PAS marquer la fiche comme scannée — sinon l'échec la gèlerait
 * jusqu'au seuil de fraîcheur (180 jours par défaut).
 */
final class EnrichissementIndisponibleException extends \RuntimeException
{
}
