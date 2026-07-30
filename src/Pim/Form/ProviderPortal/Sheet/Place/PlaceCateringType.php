<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceCateringDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\CuisineChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MealServiceChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceCateringType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('restaurantCount', NumberType::class)
            ->add('diningRoomCount', NumberType::class)
            ->add('overallDiningCount', NumberType::class)
            ->add('sittingDiningCount', NumberType::class)
            ->add('withDanceEvening', YesNoType::class)
            ->add('withCocktailParty', YesNoType::class)
            ->add('withLocalCaterer', YesNoType::class)
            ->add('withExternalCaterer', YesNoType::class)
            ->add('selfCatererAuthorized', YesNoType::class)
            ->add('exclusivityAuthorized', YesNoType::class)
            ->add('musicEndTime', TextType::class, ['required' => false])
            ->add('cuisines', ChoiceType::class, [
                'multiple' => true,
                'choices' => CuisineChoices::getChoices(),
            ])
            ->add('mealServices', ChoiceType::class, [
                'multiple' => true,
                'choices' => MealServiceChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceCateringDTO::class,
            'label_format' => 'form.sheet.place.catering.%name%.label',
        ]);
    }
}
