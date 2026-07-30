<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractType<string>
 */
class WysiwygType extends AbstractType
{
    public const DEFAULT_MAX_LENGTH = 1000;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['height'] = $options['height'] ?? 160;
        $view->vars['max_length'] = $options['max_length'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined('height');
        $resolver->setAllowedTypes('height', 'int');

        $resolver->setDefault('max_length', self::DEFAULT_MAX_LENGTH);
        $resolver->setAllowedTypes('max_length', 'int');
    }

    public function getParent()
    {
        return TextareaType::class;
    }
}
