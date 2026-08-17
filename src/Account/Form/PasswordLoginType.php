<?php

declare(strict_types=1);

namespace App\Account\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Seconde étape du parcours de connexion (« Connectez-vous ») : le mot de
 * passe, l'e-mail retenu voyageant en champ caché. Le préfixe vide fait
 * correspondre les champs aux paramètres du `form_login` du firewall
 * (email / password / _csrf_token) — pas d'authenticator maison.
 *
 * @extends AbstractType<array{email?: string, password?: string}>
 */
final class PasswordLoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', HiddenType::class)
            ->add('password', PasswordType::class, [
                'label' => 'Saisissez votre mot de passe',
                'attr' => ['placeholder' => 'Saisissez votre mot de passe', 'autocomplete' => 'current-password', 'autofocus' => true],
            ])
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
