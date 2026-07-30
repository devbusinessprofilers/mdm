<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Form\ProviderPortal\TagSelectType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceServicesDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\AmenityTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\FacilityTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MeetingEquipmentTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\WellnessTagOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceServicesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amenities', TagSelectType::class, [
                'required' => false,
                'tag_options' => AmenityTagOptions::getTagOptions(),
            ])
            ->add('meetingEquipments', TagSelectType::class, [
                'required' => false,
                'tag_options' => MeetingEquipmentTagOptions::getTagOptions(),
            ])
            ->add('facilities', TagSelectType::class, [
                'required' => false,
                'tag_options' => FacilityTagOptions::getTagOptions(),
            ])
            ->add('wellnessList', TagSelectType::class, [
                'required' => false,
                'tag_options' => WellnessTagOptions::getTagOptions(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceServicesDTO::class,
            'label_format' => 'form.sheet.place.services.%name%.label',
        ]);
    }
}
