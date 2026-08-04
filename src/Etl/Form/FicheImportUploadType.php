<?php

declare(strict_types=1);

namespace App\Etl\Form;

use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/** @extends AbstractType<array<string, mixed>> */
final class FicheImportUploadType extends AbstractType
{
    private const TYPE_LABELS = [
        'Lieu' => TypeFiche::Lieu,
        'Activité' => TypeFiche::Activite,
        'Restaurant' => TypeFiche::Restaurant,
        'Service événementiel' => TypeFiche::ServiceEvenementiel,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type de fiche',
                'choices' => self::TYPE_LABELS,
                'choice_value' => static fn (?TypeFiche $type): ?string => $type?->value,
                'constraints' => [new NotNull()],
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier XLSX (feuille Données) ou CSV rempli à partir du modèle',
                'constraints' => [
                    new NotBlank(message: 'Sélectionnez un fichier XLSX ou CSV.'),
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/zip',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ],
                        mimeTypesMessage: 'Le fichier doit être un XLSX ou un CSV.',
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Lancer l’import']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
