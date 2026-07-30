<?php

declare(strict_types=1);

namespace App\Account\Form;

use App\Account\Enum\FicheAffiliationRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CollaborateurInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'required' => false])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'required' => false])
            ->add('language', TextType::class, ['label' => 'Langue', 'data' => 'fr'])
            ->add('fiche', FicheAutocompleteType::class)
            ->add('role', ChoiceType::class, self::roleOptions())
            ->add('receivesRequests', CheckboxType::class, ['label' => 'Reçoit les demandes', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'Inviter']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'csrf_token_id' => 'admin-invite-collaborateur']);
    }

    /** @return array<string, mixed> */
    public static function roleOptions(): array
    {
        return [
            'label' => 'Rôle',
            'choices' => array_combine(
                array_map(static fn (FicheAffiliationRole $role): string => ucfirst($role->value), FicheAffiliationRole::cases()),
                FicheAffiliationRole::cases(),
            ),
            'choice_value' => static fn (?FicheAffiliationRole $role): ?string => $role?->value,
        ];
    }
}
