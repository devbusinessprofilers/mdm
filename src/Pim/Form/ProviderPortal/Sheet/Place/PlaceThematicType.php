<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceThematicDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\AtmosphereChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EnvironmentChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\ThematicChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceThematicType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('thematic', ChoiceType::class, [
                'multiple' => true,
                'choices' => ThematicChoices::getChoices(),
            ])
            ->add('environment', ChoiceType::class, [
                'multiple' => true,
                'choices' => EnvironmentChoices::getChoices(),
            ])
            ->add('atmosphere', ChoiceType::class, [
                'multiple' => true,
                'choices' => AtmosphereChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceThematicDTO::class,
            'label_format' => 'form.sheet.place.thematic.%name%.label',
        ]);
    }
}
