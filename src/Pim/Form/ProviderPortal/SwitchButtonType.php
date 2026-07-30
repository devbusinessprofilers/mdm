<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SwitchButtonType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['inverted'] = $options['inverted'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inverted' => false,
            'false_values' => [null, '0', 'false'],
        ]);

        $resolver->setAllowedTypes('inverted', 'bool');
    }

    public function getParent(): string
    {
        return CheckboxType::class;
    }
}
