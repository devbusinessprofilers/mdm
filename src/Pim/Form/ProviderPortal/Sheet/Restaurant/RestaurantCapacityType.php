<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Restaurant;

use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantCapacityDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantCapacityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sittingCapacity', NumberType::class)
            ->add('privateCapacity', NumberType::class)
            ->add('feastCapacity', NumberType::class)
            ->add('cocktailCapacity', NumberType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantCapacityDTO::class,
            'label_format' => 'form.sheet.restaurant.capacity.%name%.label',
        ]);
    }
}
