<?php

declare(strict_types=1);

namespace App\Account\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class AffiliationType extends AbstractType
{
    /** Aide du drapeau « contact de repli » : affichée en help de formulaire et en infobulle des tableaux. */
    public const AIDE_REPLI = 'Contact provisoire du service référencement quand la fiche n\'a pas de contact prestataire : les demandes lui parviennent mais aucun accès extranet ne lui est envoyé. Réservé aux adresses @businessprofilers.fr.';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_fiche']) {
            $builder->add('fiche', FicheAutocompleteType::class);
        }
        $builder
            ->add('role', ChoiceType::class, CollaborateurInvitationType::roleOptions())
            ->add('receivesRequests', CheckboxType::class, [
                'label' => $options['with_droits'] ? 'Traite les demandes' : 'Reçoit les demandes',
                'required' => false,
            ]);
        if ($options['with_droits']) {
            $builder
                ->add('traiteContenus', CheckboxType::class, ['label' => 'Traite les contenus', 'required' => false])
                ->add('traitePaiements', CheckboxType::class, ['label' => 'Traite les paiements', 'required' => false]);
        }
        // La case n'est proposée que pour les adresses internes : le repli est
        // par définition quelqu'un du service référencement, pas du prestataire.
        if ($options['with_repli']) {
            $builder->add('repli', CheckboxType::class, [
                'label' => 'Contact de repli', 'required' => false, 'help' => self::AIDE_REPLI,
            ]);
        }
        $builder->add('submit', SubmitType::class, ['label' => $options['button_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'with_fiche' => false,
            'with_droits' => false,
            'with_repli' => false,
            'button_label' => 'Enregistrer',
        ]);
        $resolver->setAllowedTypes('with_fiche', 'bool');
        $resolver->setAllowedTypes('with_droits', 'bool');
        $resolver->setAllowedTypes('with_repli', 'bool');
        $resolver->setAllowedTypes('button_label', 'string');
    }
}
