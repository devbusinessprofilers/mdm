<?php

namespace App\Pim\Form\ProviderPortal\Sheet;

use App\Pim\Enum\ProviderPortal\VatEnum;
use App\Pim\Form\ProviderPortal\EnumType;
use App\Pim\Form\ProviderPortal\MoneyType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\VatPriceDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VatPriceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $vatRates = array_reduce(
            VatEnum::cases(),
            function (array $acc, VatEnum $vat) {
                $acc[$vat->value] = $vat->getVatRate();

                return $acc;
            },
            [],
        );

        $view->vars['vat_rates'] = $vatRates;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('exTaxAmount', MoneyType::class)
            ->add('taxAmount', MoneyType::class, [
                'required' => false,
                'disabled' => true,
            ])
            ->add('vat', EnumType::class, [
                'expanded' => true,
                'enum' => VatEnum::class,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VatPriceDTO::class,
            'label_format' => 'form.sheet.vatPrice.%name%.label',
        ]);
    }
}
