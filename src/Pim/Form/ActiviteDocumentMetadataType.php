<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string,mixed>> */
final class ActiviteDocumentMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('title', TextType::class, [
            'label' => 'Titre',
            'required' => false,
        ])
            ->add('source', TextType::class, [
                'label' => 'Source',
                'required' => false,
            ])
            ->add('rightsGranted', CheckboxType::class, [
                'label' => 'Droits d’utilisation validés',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer les métadonnées',
            ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['data_class' => null]);
    }
}
