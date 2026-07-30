<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class YesNoType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('expanded', true);
        $resolver->setDefault('choices', [
            'global.yes' => true,
            'global.no' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
