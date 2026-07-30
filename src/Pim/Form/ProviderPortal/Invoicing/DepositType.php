<?php

namespace App\Pim\Form\ProviderPortal\Invoicing;

use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\DepositDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\DepositDelayChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DepositType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('delay', ChoiceType::class, [
                'choices' => DepositDelayChoices::getChoices(),
            ])
            ->add('percent', NumberType::class, [
                'min_value' => 0,
                'max_value' => 100,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DepositDTO::class,
            'label_format' => 'form.invoicing.deposit.%name%.label',
        ]);
    }
}
