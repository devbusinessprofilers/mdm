<?php

namespace App\Pim\Form\ProviderPortal\Sheet\MealTray;

use App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray\MealTrayDescriptionDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\CSRCommitmentChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\CuisineChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MealTrayDescriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class)
            ->add('cuisine', ChoiceType::class, [
                'choices' => CuisineChoices::getChoices(),
            ])
            ->add('csrCommitments', ChoiceType::class, [
                'multiple' => true,
                'choices' => CSRCommitmentChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MealTrayDescriptionDTO::class,
            'label_format' => 'form.sheet.mealTray.description.%name%.label',
        ]);
    }
}
