<?php

declare(strict_types=1);

namespace App\Pim\Service\Wikidata;

/**
 * Résultat d'une détection de chaîne dans un nom d'établissement : l'enseigne
 * réellement présente dans le nom (« Mercure » — c'est elle qu'on propose,
 * elle correspond aux marques de la LOV), et le groupe propriétaire
 * (« Accor »), conservé en information.
 */
final readonly class ChaineDetectee
{
    public function __construct(
        public string $enseigne,
        public string $groupe,
    ) {
    }
}
