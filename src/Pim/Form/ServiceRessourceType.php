<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;

/** @extends AbstractType<\App\Pim\Entity\Lieu\RessourceLieu> */
final class ServiceRessourceType extends AbstractType
{
    public function getParent(): string
    {
        return ActiviteRessourceType::class;
    }
}
