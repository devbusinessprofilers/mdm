<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Lieu\Salle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

/** @extends AbstractType<array<string, mixed>> */
final class LieuDocumentUploadType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('documents', FileType::class, [
                'label' => 'Fichiers PDF, JPEG ou PNG',
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new All([
                        new File(
                            mimeTypes: [
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                            ],
                        ),
                    ]),
                ],
            ])
            ->add('usage', ChoiceType::class, [
                'choices' => DocumentUsage::choices(),
                'choice_value' => static fn (
                    ?DocumentUsage $usage,
                ): ?string => $usage?->value,
            ])
            ->add('salle', EntityType::class, [
                'class' => Salle::class,
                'choices' => $options['salles'],
                'choice_label' => static fn (
                    Salle $salle,
                ): string => $salle->nom(),
                'placeholder' => 'Aucune',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
            ])
            ->add('source', TextType::class, ['required' => false])
            ->add('rightsGranted', CheckboxType::class, [
                'label' => 'Droits d’utilisation validés',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Ajouter les documents',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'salles' => []]);
        $resolver->setAllowedTypes('salles', 'array');
    }
}
