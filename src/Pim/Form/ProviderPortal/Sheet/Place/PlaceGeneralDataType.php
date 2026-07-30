<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\ClosingPeriodType;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Form\ProviderPortal\OpeningHoursType;
use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceGeneralDataDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EventChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\GroupChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\TypologyChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceGeneralDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'disabled' => true,
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
            ->add('typologies', ChoiceType::class, [
                'disabled' => true,
                'multiple' => true,
                'choices' => TypologyChoices::getChoices(),
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
            ->add('groups', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'choices' => GroupChoices::getChoices(),
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
            ->add('events', ChoiceType::class, [
                'multiple' => true,
                'required' => false,
                'choices' => EventChoices::getChoices(),
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
            ->add('erp', TextType::class, [
                'required' => false,
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                    TextTypeAttributeEnum::TOOLTIP->value => 'form.sheet.place.erp.tooltip',
                ],
            ])
            ->add('website', TextType::class, [
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
            ->add('privatisable', YesNoType::class)
            ->add('openingHours', OpeningHoursType::class, ['required' => false])
            ->add('closingPeriods', CollectionType::class, [
                'entry_type' => ClosingPeriodType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'add_button_label' => 'form.closing_periods.add.label',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceGeneralDataDTO::class,
            'label_format' => 'form.sheet.place.%name%.label',
        ]);
    }
}
