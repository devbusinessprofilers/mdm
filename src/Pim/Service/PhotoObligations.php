<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use App\Shared\Service\ParametreProviderInterface;

/**
 * Obligations photos par type de fiche, surchargeables dans /admin/parametres.
 * Source unique des seuils : validation Draft/Submission, écrans et API photos,
 * et politique de diffusion marketplace (module Etl) lisent tous ici.
 * Modifier un seuil n'est pas rétroactif : les fiches déjà soumises ou
 * diffusées ne sont réévaluées qu'à leur prochaine mutation.
 */
final readonly class PhotoObligations
{
    public function __construct(private ParametreProviderInterface $parametres)
    {
    }

    public function minimum(TypeFiche $type): int
    {
        return $this->parametres->int(TypeFiche::Lieu === $type ? 'photos.min_lieu' : 'photos.min_autres');
    }

    public function maximum(TypeFiche $type): int
    {
        return $this->parametres->int(TypeFiche::Lieu === $type ? 'photos.max_lieu' : 'photos.max_autres');
    }
}
