<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity\Price;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\Price\ActivityOptionDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityOptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('capacity', TextType::class)
            ->add('price', TextType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityOptionDTO::class,
            'label_format' => 'form.sheet.activity.price.option.%name%.label',
        ]);
    }
}
