<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\ChoiceTypeAttributeEnum;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityDescriptionDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\PurposeChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityDescriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class)
            ->add('content', TextareaType::class, ['required' => false])
            ->add('purposeList', ChoiceType::class, [
                'multiple' => true,
                'choices' => PurposeChoices::getChoices(),
                'attr' => [
                    ChoiceTypeAttributeEnum::LIMIT->value => 5,
                ],
            ])
            ->add('extra1', TextType::class, ['required' => false])
            ->add('extra2', TextType::class, ['required' => false])
            ->add('extra3', TextType::class, ['required' => false])
            ->add('extra4', TextType::class, ['required' => false])
            ->add('extra5', TextType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityDescriptionDTO::class,
            'label_format' => 'form.sheet.activity.description.%name%.label',
        ]);
    }
}
