<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Lieu\Salle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class LieuDocumentMetadataType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
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
            ->add('keywords', TextareaType::class, ['label' => 'Mots-clés', 'required' => false])
            ->add('rightsExpiresAt', DateType::class, ['label' => 'Échéance des droits', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer les métadonnées',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'salles' => []]);
        $resolver->setAllowedTypes('salles', 'array');
    }
}
