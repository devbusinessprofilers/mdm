<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType as SymfonyNumberType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Allows to define a number input using mask input.
 *
 * @see https://imask.js.org/guide.html#masked-number
 *
 * @template-extends AbstractType<string>
 */
class NumberType extends AbstractType
{
    private const DEFAULT_STEP = 1;
    private const DEFAULT_SCALE = 0;
    private const DEFAULT_RADIX = ',';
    private const DEFAULT_THOUSANDS_SEPARATOR = ' ';

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['step'] = $options['step'];
        $view->vars['scale'] = $options['scale'];
        $view->vars['min_value'] = $options['min_value'] ?? null;
        $view->vars['max_value'] = $options['max_value'] ?? null;
        $view->vars['radix'] = $options['radix'] ?? null;
        $view->vars['thousands_separator'] = $options['thousands_separator'];
        $view->vars['pad_fractional_zeros'] = $options['pad_fractional_zeros'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['radix', 'thousands_separator', 'pad_fractional_zeros']);
        $resolver->setDefined(['min_value', 'max_value']);

        $resolver->setAllowedTypes('min_value', ['int', 'float', 'null']);
        $resolver->setAllowedTypes('max_value', ['int', 'float', 'null']);
        $resolver->setAllowedTypes('radix', 'string');
        $resolver->setAllowedTypes('thousands_separator', 'string');
        $resolver->setAllowedTypes('pad_fractional_zeros', 'bool');

        $resolver->setNormalizer('thousands_separator', function (Options $options, $value) {
            if ($options->offsetGet('radix') === $value) {
                throw new \InvalidArgumentException('Radix and thousands separator mus be different.');
            }

            return $value;
        });

        $resolver->setDefaults([
            'step' => self::DEFAULT_STEP,
            'scale' => self::DEFAULT_SCALE,
            'radix' => self::DEFAULT_RADIX,
            'thousands_separator' => self::DEFAULT_THOUSANDS_SEPARATOR,
            'pad_fractional_zeros' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'portal_number';
    }

    public function getParent(): string
    {
        return SymfonyNumberType::class;
    }
}
