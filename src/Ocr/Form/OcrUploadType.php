<?php

declare(strict_types=1);

namespace App\Ocr\Form;

use App\Dam\Enum\DocumentUsage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/** @extends AbstractType<array{category: DocumentUsage|null, document: mixed}|null> */
final class OcrUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, ['label' => 'Catégorie documentaire', 'choices' => $options['category_choices']])
            ->add('document', FileType::class, ['label' => 'PDF à analyser', 'constraints' => [new File(maxSize: '50M', mimeTypes: ['application/pdf'], mimeTypesMessage: 'Un PDF réel est requis.')]])
            ->add('submit', SubmitType::class, ['label' => 'Envoyer pour extraction']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('category_choices')->setAllowedTypes('category_choices', 'array');
        $resolver->setDefaults(['csrf_token_id' => 'ocr-upload']);
    }
}
