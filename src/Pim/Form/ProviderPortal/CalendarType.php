<?php

namespace App\Pim\Form\ProviderPortal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['date_min'] = $options['date_min'] ?? null;
        $view->vars['date_max'] = $options['date_max'] ?? null;
        $view->vars['format'] = $options['format'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['date_min', 'date_max']);
        $resolver->setAllowedTypes('date_min', [\DateTime::class, 'null']);
        $resolver->setAllowedTypes('date_max', [\DateTime::class, 'null']);

        $resolver->setDefaults([
            'widget' => 'single_text',
            'format' => 'dd/MM/yyyy',
            'html5' => false,
        ]);
    }

    public function getParent(): string
    {
        return DateType::class;
    }
}
