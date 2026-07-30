<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoneyType extends AbstractType
{
    private const DEFAULT_STEP = 0.01;
    private const DEFAULT_SCALE = 2;
    private const DEFAULT_CURRENCY_ICON = 'currency-euro';

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['currency_icon'] = $options['currency_icon'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('currency_icon');
        $resolver->setAllowedTypes('currency_icon', 'string');

        $resolver->setDefaults([
            'currency_icon' => self::DEFAULT_CURRENCY_ICON,
            'step' => self::DEFAULT_STEP,
            'scale' => self::DEFAULT_SCALE,
            'min_value' => 0,
            'pad_fractional_zeros' => true,
        ]);
    }

    public function getParent(): string
    {
        return NumberType::class;
    }
}
