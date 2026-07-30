<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentFileType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['file_name'] = $options['file_name'] ?? null;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined(['accepted_type', 'file_name']);
        $resolver->setAllowedTypes('accepted_type', [AcceptedTypeEnum::class, 'null']);
        $resolver->setAllowedTypes('file_name', ['string', 'null']);

        $resolver->setNormalizer('attr', fn (Options $options, $value) => $this->resolveExtensions($options->offsetGet('accepted_type'), $value));
        $resolver->setNormalizer('constraints', fn (Options $options, $value) => $this->resolveConstraints($options->offsetGet('accepted_type'), $value));

        $resolver->setDefaults([
            'label' => false,
            'accepted_type' => null,
        ]);
    }

    public function getParent(): string
    {
        return FileType::class;
    }

    protected function resolveExtensions(?AcceptedTypeEnum $acceptedType, array $value = []): array
    {
        if (null !== $acceptedType) {
            $value['accept'] = implode(',', $acceptedType->getExtensions());
        }

        return $value;
    }

    protected function resolveConstraints(?AcceptedTypeEnum $acceptedType, $value): array
    {
        if (!empty($value)) {
            return $value;
        }

        return (null === $acceptedType || AcceptedTypeEnum::ALL === $acceptedType)
            ? []
            : [new File(['mimeTypes' => $acceptedType->getMimeTypes()])]
        ;
    }
}
