<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Account\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Sélection et action groupée de la liste du référentiel : les cases des
 * lignes de la page, la bascule « tout le résultat filtré », l'action et son
 * bouton. Les plafonds sont contrôlés côté contrôleur/service.
 *
 * @extends AbstractType<array{ids: list<string>, tout: bool, action: ?string, contributeur: ?User, sites: list<int>}>
 */
final class ReferentielSelectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ids', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['ids_choices'],
            ])
            ->add('tout', CheckboxType::class, [
                'label' => 'Appliquer à tout le résultat filtré',
                'required' => false,
            ])
            ->add('action', ChoiceType::class, [
                'label' => 'Action',
                'placeholder' => 'Choisir une action…',
                'choices' => [
                    'Valider' => 'valider',
                    'Publier' => 'publier',
                    'Archiver (dépublier)' => 'archiver',
                    'Exporter (CSV)' => 'exporter',
                    'Envoyer les accès extranet' => 'acces',
                    'Assigner un contributeur' => 'contributeur',
                    'Attribuer la visibilité' => 'visibilite',
                ],
            ])
            ->add('contributeur', EntityType::class, [
                'label' => 'Contributeur',
                'class' => User::class,
                'choice_label' => 'userIdentifier',
                'required' => false,
                'placeholder' => '— pour « Assigner un contributeur » —',
                'choices' => $options['contributeurs'],
            ])
            ->add('sites', ChoiceType::class, [
                'label' => 'Sites de diffusion',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['sites_choices'],
            ])
            ->add('appliquer', SubmitType::class, ['label' => 'Appliquer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'POST',
            'csrf_token_id' => 'referentiel-selection',
            'ids_choices' => [],
            'contributeurs' => [],
            'sites_choices' => [],
        ]);
        $resolver->setAllowedTypes('ids_choices', 'array');
        $resolver->setAllowedTypes('contributeurs', 'array');
        $resolver->setAllowedTypes('sites_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'selection';
    }
}
