<?php

declare(strict_types=1);

namespace App\Account\Form;

use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<array{password?: string}> */
final class NewPasswordType extends AbstractType
{
    public function __construct(private readonly ParametreProviderInterface $parametres)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $longueurMin = max(1, $this->parametres->int('compte.mot_de_passe_longueur_min'));
        $builder
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'help' => sprintf('Choisissez un mot de passe d’au moins %d caractères.', $longueurMin),
                    // `data-with-control` : jauge et règles affichées à la
                    // frappe (composant Password du portail, opt-in du thème).
                    'attr' => ['autocomplete' => 'new-password', 'placeholder' => 'Mot de passe', 'data-with-control' => true],
                ],
                'second_options' => [
                    'label' => 'Confirmation du nouveau mot de passe',
                    'attr' => ['autocomplete' => 'new-password', 'placeholder' => 'Confirmation du nouveau mot de passe'],
                ],
                'constraints' => [
                    new Assert\Length(min: $longueurMin, max: 4096),
                    new Assert\NotCompromisedPassword(),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => $options['button_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'button_label' => 'Enregistrer le mot de passe',
        ]);
        $resolver->setAllowedTypes('button_label', 'string');
    }
}
