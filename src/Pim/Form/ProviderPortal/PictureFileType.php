<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PictureFileType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['picture_url'] = $options['picture_url'] ?? null;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined('picture_url');
        $resolver->setAllowedTypes('picture_url', ['string', 'null']);
        $resolver->setDefaults([
            'attr' => [
                'accept' => implode(',', AcceptedTypeEnum::IMAGES->getExtensions()),
            ],
            'constraints' => [
                new File(['mimeTypes' => AcceptedTypeEnum::IMAGES->getMimeTypes()]),
            ],
        ]);
    }

    public function getParent(): string
    {
        return FileType::class;
    }
}
