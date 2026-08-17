<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class LieuPhotoUploadType extends AbstractType
{
    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('photos', FileType::class, [
            'label' => 'Déposez vos images ici',
            'multiple' => true,
            'mapped' => false,
            // Input masqué : le libellé de la zone de dépôt et la tuile
            // « Ajouter un média » ouvrent le sélecteur, l'envoi part au change.
            'attr' => ['accept' => 'image/jpeg,image/png,image/webp', 'class' => 'sr-only', 'data-lieu-media-target' => 'input', 'data-action' => 'change->lieu-media#upload'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
