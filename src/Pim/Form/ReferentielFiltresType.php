<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Panneau de filtres de la liste du référentiel. Formulaire GET sans CSRF :
 * l'état du filtre vit dans l'URL, ce qui rend les vues enregistrables et les
 * liens partageables.
 *
 * @extends AbstractType<ReferentielFiltres>
 */
final class ReferentielFiltresType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', SearchType::class, [
                'label' => 'Recherche',
                'required' => false,
                'attr' => ['placeholder' => 'Nom, ville, identifiant…'],
            ])
            ->add('gammes', ChoiceType::class, [
                'label' => 'Gamme',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                // Plateaux repas (Traiteur) : hors de cette version du MDM.
                'choices' => [
                    'Lieux' => TypeFiche::Lieu,
                    'Restaurants' => TypeFiche::Restaurant,
                    'Activités' => TypeFiche::Activite,
                    'Prestataires de services' => TypeFiche::ServiceEvenementiel,
                ],
                'choice_value' => static fn (?TypeFiche $type): ?string => $type?->value,
            ])
            ->add('statuts', ChoiceType::class, [
                'label' => 'Statut du cycle de vie',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'En cours' => StatutFiche::EnCours,
                    'En attente de validation' => StatutFiche::EnAttenteValidation,
                    'Validée' => StatutFiche::Validee,
                    'Publiée' => StatutFiche::Publiee,
                    'Archivée' => StatutFiche::Archivee,
                ],
                'choice_value' => static fn (?StatutFiche $status): ?string => $status?->value,
            ])
            ->add('completudes', ChoiceType::class, [
                'label' => 'Complétude',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Complet (≥ 75 %)' => 'complet',
                    'Publiable (60 – 74 %)' => 'publiable',
                    'Insuffisant (< 60 %)' => 'insuffisant',
                ],
            ])
            ->add('canaux', ChoiceType::class, [
                'label' => 'Diffusion par canal',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Sur 20 canaux et plus' => 'c20',
                    'De 5 à 19 canaux' => 'c5',
                    'De 1 à 4 canaux' => 'c1',
                    'Aucun canal' => 'c0',
                ],
            ])
            ->add('pays', ChoiceType::class, [
                'label' => 'Pays',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['pays_choices'],
            ])
            ->add('dates', ChoiceType::class, [
                'label' => 'Dates',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Créées cette semaine' => 'creees_semaine',
                    'Modifiées aujourd\'hui' => 'modifiees_jour',
                    'Sans modification depuis 6 mois' => 'sans_modif_6m',
                ],
            ])
            ->add('formes', ChoiceType::class, [
                'label' => 'Écarts de forme',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Localisations sans code pays' => 'sans_pays',
                    'Localisations sans GPS' => 'sans_gps',
                    'Fiches sans libellé' => 'sans_libelle',
                ],
            ])
            ->add('contributeurs', ChoiceType::class, [
                'label' => 'Contributeur',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['contributeurs_choices'],
            ])
            ->add('ia', CheckboxType::class, [
                'label' => 'Valeurs IA à arbitrer',
                'required' => false,
            ])
            ->add('repli', CheckboxType::class, [
                'label' => 'Contact de repli',
                'required' => false,
            ])
            ->add('premium', CheckboxType::class, [
                'label' => 'Adhérent Business Premium',
                'required' => false,
            ])
            ->add('valeurs', ChoiceType::class, [
                'label' => 'Catégorie et thématiques',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['valeurs_choices'],
            ])
            ->add('filtrer', SubmitType::class, ['label' => 'Filtrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReferentielFiltres::class,
            'method' => 'GET',
            'csrf_protection' => false,
            // Les cases décochées doivent pouvoir vider un filtre pré-rempli.
            'allow_extra_fields' => true,
            'pays_choices' => [],
            'valeurs_choices' => [],
            'contributeurs_choices' => [],
        ]);
        $resolver->setAllowedTypes('contributeurs_choices', 'array');
        $resolver->setAllowedTypes('pays_choices', 'array');
        $resolver->setAllowedTypes('valeurs_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        // Des clés d'URL courtes et stables : l'état du filtre est partageable.
        return 'f';
    }
}
