<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Restaurant;

use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantFacilityDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\FacilityChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantFacilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('services', ChoiceType::class, [
                'multiple' => true,
                'choices' => FacilityChoices::getChoices(),
            ])
            ->add('withDancingFloor', YesNoType::class)
            ->add('withWifi', YesNoType::class)
            ->add('withPrivacyMediaRoom', YesNoType::class)
            ->add('withMicrophone', YesNoType::class)
            ->add('withOnSiteParking', YesNoType::class)
            ->add('withNearbyParking', YesNoType::class)
            ->add('withValetParking', YesNoType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantFacilityDTO::class,
            'label_format' => 'form.sheet.restaurant.facility.%name%.label',
        ]);
    }
}
