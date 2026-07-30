<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Model\ProviderPortal\DTO\CateringFormulaDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\CateringFormulaContentChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CateringFormulaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('minimumParticipant', NumberType::class)
            ->add('minimumPrice', MoneyType::class)
            ->add('cateringFormulaContents', ChoiceType::class, [
                'multiple' => true,
                'choices' => CateringFormulaContentChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CateringFormulaDTO::class,
            'label_format' => 'form.catering_formula.%name%.label',
        ]);
    }
}
