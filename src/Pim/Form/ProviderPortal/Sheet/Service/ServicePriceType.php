<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Service;

use App\Pim\Form\ProviderPortal\MoneyType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServicePriceDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicePriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('perServicePrice', MoneyType::class)
            ->add('perPersonPrice', MoneyType::class)
            ->add('perDayPrice', MoneyType::class)
            ->add('perHalfDayPrice', MoneyType::class)
            ->add('perHourPrice', MoneyType::class)
            ->add('onDemandPrice', MoneyType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServicePriceDTO::class,
            'label_format' => 'form.sheet.service.price.%name%.label',
        ]);
    }
}
