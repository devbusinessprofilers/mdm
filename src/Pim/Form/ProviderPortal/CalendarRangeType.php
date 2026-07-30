<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Form\DataTransformer\ProviderPortal\CalendarRangeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarRangeType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['date_min'] = $options['date_min'] ?? null;
        $view->vars['date_max'] = $options['date_max'] ?? null;
        $view->vars['separator'] = $options['separator'];
        $view->vars['format'] = $options['format'];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CalendarRangeTransformer($options['format'], $options['separator']));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['date_min', 'date_max', 'format', 'separator']);
        $resolver->setAllowedTypes('date_min', [\DateTime::class, 'null']);
        $resolver->setAllowedTypes('date_min', [\DateTime::class, 'null']);
        $resolver->setAllowedTypes('format', 'string');
        $resolver->setAllowedTypes('separator', 'string');

        $resolver->setDefaults([
            'format' => 'd/m/Y',
            'separator' => ' - ',
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
