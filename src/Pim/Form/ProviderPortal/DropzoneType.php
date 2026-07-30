<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Model\ProviderPortal\Form\Dropzone\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class DropzoneType extends AbstractType
{
    private const DEFAULT_MAX_FILE_COUNT = 5;
    private const DEFAULT_FILE_SIZE_MAX = '5M';

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['documents'] = $options['documents'];
        $view->vars['max_file_count'] = $options['max_file_count'];
        $view->vars['file_max_size'] = $options['file_max_size'];
        $view->vars['image_min_width'] = $options['image_min_width'] ?? null;
        $view->vars['image_min_height'] = $options['image_min_height'] ?? null;
        $view->vars['with_preview'] = (AcceptedTypeEnum::IMAGES === $options['accepted_type']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['accepted_type', 'max_file_count', 'file_max_size', 'documents']);
        $resolver->setDefined(['image_min_width', 'image_min_height']);

        $resolver->setAllowedTypes('accepted_type', AcceptedTypeEnum::class);
        $resolver->setAllowedTypes('documents', sprintf('%s[]', Document::class));
        $resolver->setAllowedTypes('file_max_size', 'string');
        $resolver->setAllowedTypes('max_file_count', 'int');
        $resolver->setAllowedTypes('image_min_width', ['int', 'null']);
        $resolver->setAllowedTypes('image_min_height', ['int', 'null']);

        $resolver->setAllowedValues('max_file_count', fn (int $value) => $value > 0);

        $resolver->setNormalizer('attr', fn (Options $options, $value) => ['accept' => implode(',', $options->offsetGet('accepted_type')->getExtensions())]);
        $resolver->setNormalizer('constraints', fn (Options $options, $value) => $this->resolveConstraints(
            $options->offsetGet('accepted_type'),
            $options->offsetGet('file_max_size'),
            $options->offsetGet('image_min_width'),
            $options->offsetGet('image_min_height'),
        ));

        $resolver->setDefaults([
            'multiple' => true,
            'documents' => [],
            'max_file_count' => self::DEFAULT_MAX_FILE_COUNT,
            'file_max_size' => self::DEFAULT_FILE_SIZE_MAX,
            'image_min_width' => null,
            'image_min_height' => null,
        ]);
    }

    public function getParent(): string
    {
        return FileType::class;
    }

    /**
     * @return array<Constraint>
     */
    protected function resolveConstraints(AcceptedTypeEnum $acceptedType, string $maxSize, ?int $minWidth, ?int $minHeight): array
    {
        $constraints = [
            new File([
                'mimeTypes' => $acceptedType->getMimeTypes(),
                'maxSize' => $maxSize,
            ]),
        ];

        if (null !== $minHeight || null !== $minWidth) {
            $constraints[] = new Image(array_filter([
                'minWidth' => $minWidth,
                'minHeight' => $minHeight,
            ]));
        }

        return $constraints;
    }
}
