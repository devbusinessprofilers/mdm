<?php

namespace App\Pim\Form\ProviderPortal\Media;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaCollectionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['maxFileCount'] = $options['maxFileCount'];
        $view->vars['fileMaxSize'] = $options['fileMaxSize'];
        $view->vars['imageMinWidth'] = $options['imageMinWidth'] ?? null;
        $view->vars['imageMinHeight'] = $options['imageMinHeight'] ?? null;
        $view->vars['acceptedFiles'] = $options['for_picture']
            ? implode(',', AcceptedTypeEnum::IMAGES->getExtensions())
            : implode(',', AcceptedTypeEnum::DOCUMENTS->getExtensions())
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['for_picture', 'maxFileCount', 'fileMaxSize']);
        $resolver->setDefined(['imageMinWidth', 'imageMinHeight']);
        $resolver->setAllowedTypes('for_picture', 'bool');

        $resolver->setDefault('entry_type', fn (Options $options) => $options->offsetGet('for_picture') ? PictureType::class : DocumentType::class);

        $resolver->setDefaults([
            'label' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);

        $resolver->setAllowedValues('allow_add', true);
        $resolver->setAllowedValues('allow_delete', true);
        $resolver->setAllowedValues('entry_type', [PictureType::class, DocumentType::class]);
    }

    public function getParent(): string
    {
        return CollectionType::class;
    }
}
