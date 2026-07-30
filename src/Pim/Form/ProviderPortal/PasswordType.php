<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType as SymfonyPasswordType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractType<string>
 */
class PasswordType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['with_control'] = $options['with_control'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined('with_control');
        $resolver->setAllowedTypes('with_control', 'bool');
        $resolver->setDefault('with_control', false);
    }

    public function getBlockPrefix()
    {
        return 'provider_portal_password';
    }

    public function getParent()
    {
        return SymfonyPasswordType::class;
    }
}
