<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Model\ProviderPortal\DTO\Date\ClosingPeriodDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClosingPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('period', CalendarRangeType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClosingPeriodDTO::class,
            'label_format' => 'form.closing_period.%name%.label',
        ]);
    }
}
