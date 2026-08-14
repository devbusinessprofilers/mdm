<?php

declare(strict_types=1);

namespace App\Pim\Service;

/** Vérification des adresses françaises contre la Base Adresse Nationale. */
interface BanClientInterface extends GeocodeurAdresseInterface
{
}
