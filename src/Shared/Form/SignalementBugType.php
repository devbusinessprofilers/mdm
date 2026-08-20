<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<array<string, mixed>> */
final class SignalementBugType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'constraints' => [new NotBlank(), new Length(max: 150)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => "Décrivez le problème : ce que vous faisiez, ce qui était attendu, ce qui s'est produit.",
                'attr' => ['rows' => 10],
                'constraints' => [new NotBlank(), new Length(max: 10000)],
            ])
            // Page d'où vient l'utilisateur, capturée à l'affichage du formulaire.
            ->add('page', HiddenType::class, [
                'required' => false,
                'constraints' => [new Length(max: 500)],
            ])
            ->add('envoyer', SubmitType::class, ['label' => 'Envoyer le signalement']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
