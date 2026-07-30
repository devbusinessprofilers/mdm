<?php

declare(strict_types=1);

namespace App\Account\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array{email?: string, password?: string}> */
final class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Adresse email', 'attr' => ['autocomplete' => 'username', 'autofocus' => true]])
            ->add('password', PasswordType::class, ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'current-password']])
            ->add('submit', SubmitType::class, ['label' => 'Se connecter']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
            'method' => 'POST',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
