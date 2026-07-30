<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Restaurant;

use App\Pim\Form\ProviderPortal\ClosingPeriodType;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Form\ProviderPortal\OpeningHoursType;
use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantInformationDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\CuisineChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\DietaryPreferenceChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\EventChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\TypologyChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'disabled' => true,
            ])
            ->add('typologies', ChoiceType::class, [
                'multiple' => true,
                'choices' => TypologyChoices::getChoices(),
            ])
            ->add('cuisines', ChoiceType::class, [
                'multiple' => true,
                'choices' => CuisineChoices::getChoices(),
            ])
            ->add('dietaryPreferences', ChoiceType::class, [
                'multiple' => true,
                'choices' => DietaryPreferenceChoices::getChoices(),
            ])
            ->add('events', ChoiceType::class, [
                'multiple' => true,
                'choices' => EventChoices::getChoices(),
            ])
            ->add('website', TextType::class)
            ->add('totalExclusivityAuthorized', YesNoType::class)
            ->add('partialExclusivityAuthorized', YesNoType::class)
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
            'data_class' => RestaurantInformationDTO::class,
            'label_format' => 'form.sheet.restaurant.information.%name%.label',
        ]);
    }
}
