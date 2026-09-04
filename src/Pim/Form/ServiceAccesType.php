<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Service\ServiceAcces;
use App\Pim\Enum\TypeAccesService;

/** @extends AbstractAccesType<ServiceAcces> */
final class ServiceAccesType extends AbstractAccesType
{
    protected function classeAcces(): string
    {
        return ServiceAcces::class;
    }

    protected function typesAcces(): array
    {
        return TypeAccesService::choices();
    }
}
