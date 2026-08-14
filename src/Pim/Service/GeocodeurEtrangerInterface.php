<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Vérificateur des adresses hors de France (la BAN ne couvre que la France).
 * Les lignes portent obligatoirement le code pays ISO-2 : le géocodeur borne
 * sa recherche au pays pour écarter les homonymes.
 */
interface GeocodeurEtrangerInterface extends GeocodeurAdresseInterface
{
}
