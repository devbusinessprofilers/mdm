<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType as SymfonyCollectionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractType<string>
 */
class CollectionType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['label_typography_variant'] = $options['label_typography_variant'];
        $view->vars['label_bold'] = $options['label_bold'];
        $view->vars['information_text'] = $options['information_text'] ?? null;
        $view->vars['max_item_count'] = $options['max_item_count'] ?? 0;
        $view->vars['add_button_label'] = $options['add_button_label'];
        $view->vars['add_button_position'] = $options['add_button_position'];
        $view->vars['sortable'] = $options['sortable'];
        $view->vars['wrapper_class'] = $options['wrapper_class'];
        $view->vars['collection_class'] = $options['collection_class'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefined([
                'label_typography_variant',
                'label_bold',
                'information_text',
                'max_item_count',
                'add_button_label',
                'add_button_position',
                'sortable',
                'wrapper_class',
                'collection_class',
            ])
            ->setAllowedTypes('label_typography_variant', TypographyVariantEnum::class)
            ->setAllowedTypes('label_bold', 'bool')
            ->setAllowedTypes('max_item_count', ['int', 'null'])
            ->setAllowedTypes('information_text', ['string', 'null'])
            ->setAllowedTypes('add_button_label', 'string')
            ->setAllowedValues('add_button_position', ['start', 'end'])
            ->setAllowedTypes('sortable', 'bool')
            ->setAllowedTypes('collection_class', 'string')
            ->setDefaults([
                'label_typography_variant' => TypographyVariantEnum::SUBTITLE,
                'label_bold' => true,
                'add_button_label' => 'global.add',
                'add_button_position' => 'start',
                'sortable' => false,
                'wrapper_class' => '',
                'collection_class' => '',
            ])
        ;
    }

    public function getParent()
    {
        return SymfonyCollectionType::class;
    }
}
