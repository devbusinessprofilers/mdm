<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

/** @extends AbstractType<array<string, mixed>> */
final class LieuDocumentReplaceType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('document', FileType::class, [
                'label' => 'Nouveau fichier',
                'constraints' => [
                    new File(
                        mimeTypes: [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                        ],
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Remplacer le fichier',
            ]);
    }
}
