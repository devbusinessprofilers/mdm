<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceLeisureDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\LeisureChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceLeisureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('leisureList', ChoiceType::class, [
                'multiple' => true,
                'choices' => LeisureChoices::getChoices(),
            ])
            ->add('teamBuildings', CollectionType::class, [
                'label_typography_variant' => TypographyVariantEnum::BODY_MEDIUM,
                'label_bold' => false,
                'entry_type' => TeamBuildingType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceLeisureDTO::class,
            'label_format' => 'form.sheet.place.leisure.%name%.label',
        ]);
    }
}
