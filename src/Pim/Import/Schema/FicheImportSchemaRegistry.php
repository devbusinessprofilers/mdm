<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Enum\TypeFiche;

final readonly class FicheImportSchemaRegistry
{
    public function __construct(
        private LieuImportSchema $lieu,
        private ActiviteImportSchema $activite,
        private RestaurantImportSchema $restaurant,
        private ServiceImportSchema $service,
    ) {
    }

    public function for(TypeFiche $type): FicheImportSchemaInterface
    {
        return match ($type) {
            TypeFiche::Lieu => $this->lieu,
            TypeFiche::Activite => $this->activite,
            TypeFiche::Restaurant => $this->restaurant,
            TypeFiche::ServiceEvenementiel => $this->service,
            TypeFiche::Traiteur => throw new \DomainException('L’import Traiteur n’est pas disponible.'),
        };
    }

    /** @return list<TypeFiche> */
    public static function supportedTypes(): array
    {
        return [TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel];
    }
}
