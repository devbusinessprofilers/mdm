<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Enum\TypeAccesLieu;

/** @extends AbstractAccesType<AccesLieu> */
final class AccesLieuType extends AbstractAccesType
{
    protected function classeAcces(): string
    {
        return AccesLieu::class;
    }

    protected function typesAcces(): array
    {
        $types = [];
        foreach (TypeAccesLieu::cases() as $type) {
            $types[ucfirst(str_replace('_', ' ', $type->value))] = $type;
        }

        return $types;
    }
}
