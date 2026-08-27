<?php

declare(strict_types=1);

namespace App\Etl\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

/**
 * Import en masse (Outils) : un classeur XLSX issu de l'export du
 * référentiel, multi-feuilles (une par gamme).
 *
 * @extends AbstractType<array{file: ?\Symfony\Component\HttpFoundation\File\UploadedFile}>
 */
final class ImportMasseUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Classeur d\'export (XLSX)',
                // Zone de dépôt du design-system (glisser-déposer + carte du
                // fichier retenu), comme l'onglet documents des médias.
                'attr' => ['data-dropzone' => true, 'accept' => '.xlsx', 'data-max-files' => 1, 'data-max-size' => '20M'],
                'constraints' => [
                    new File(
                        maxSize: '20M',
                        mimeTypes: [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/octet-stream',
                        ],
                        mimeTypesMessage: 'Le fichier doit être un classeur XLSX issu de l\'export du référentiel.',
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Lancer l\'import en masse']);
    }
}
