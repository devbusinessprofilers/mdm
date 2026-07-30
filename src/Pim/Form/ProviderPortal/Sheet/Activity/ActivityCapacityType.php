<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity;

use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityCapacityDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityCapacityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minCapacity', NumberType::class)
            ->add('maxCapacity', NumberType::class)
            ->add('minDuration', NumberType::class)
            ->add('maxDuration', NumberType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityCapacityDTO::class,
            'label_format' => 'form.sheet.activity.capacity.%name%.label',
        ]);
    }
}
