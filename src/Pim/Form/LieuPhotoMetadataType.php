<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\Salle;
use App\Pim\Service\PhotoUsageCatalog;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class LieuPhotoMetadataType extends AbstractType
{
    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Hors fiche Lieu (avec_salles = false), pas d'association de salle :
        // les catégories liées aux salles disparaissent avec le champ.
        $usages = array_flip(PhotoUsageCatalog::LABELS);
        if (!$options['avec_salles']) {
            unset($usages['Salle de réunion'], $usages['Plan de salle']);
        }
        $builder
            ->add('usage', ChoiceType::class, ['label' => 'Catégorie', 'choices' => $usages])
            ->add('legende', TextType::class, ['label' => 'Légende', 'required' => false])
            ->add('source', TextType::class, ['label' => 'Source / crédit', 'required' => false])
            ->add('keywords', TextareaType::class, ['label' => 'Mots-clés', 'required' => false])
            ->add('rights_expires_at', DateType::class, ['label' => 'Échéance des droits', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable']);
        if ($options['avec_salles']) {
            $builder->add('salle_id', EntityType::class, ['label' => 'Salle', 'class' => Salle::class, 'choices' => $options['salles'], 'choice_value' => 'id', 'choice_label' => static fn (Salle $salle): string => $salle->nom() ?: 'Salle sans nom', 'placeholder' => 'Aucune', 'required' => false]);
        }
        $builder
            ->add('crop_x', IntegerType::class, ['label' => 'X', 'required' => false, 'attr' => ['min' => 0]])
            ->add('crop_y', IntegerType::class, ['label' => 'Y', 'required' => false, 'attr' => ['min' => 0]])
            ->add('crop_width', IntegerType::class, ['label' => 'Largeur', 'required' => false, 'attr' => ['min' => 1]])
            ->add('crop_height', IntegerType::class, ['label' => 'Hauteur', 'required' => false, 'attr' => ['min' => 1]])
            ->add('rotation', ChoiceType::class, ['label' => 'Rotation', 'choices' => ['0°' => 0, '90°' => 90, '180°' => 180, '270°' => 270]])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer', 'attr' => ['class' => 'btn']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_protection' => false, 'salles' => [], 'avec_salles' => true]);
        $resolver->setAllowedTypes('salles', 'array');
        $resolver->setAllowedTypes('avec_salles', 'bool');
    }
}
