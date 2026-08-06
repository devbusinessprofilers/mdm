<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<array<string, mixed>> */
final class FicheBrowseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $countries */
        $countries = $options['countries'];
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'required' => false,
                'placeholder' => 'Tous les types',
                'choices' => [
                    'Lieu' => TypeFiche::Lieu,
                    'Activité' => TypeFiche::Activite,
                    'Restaurant' => TypeFiche::Restaurant,
                    'Service' => TypeFiche::ServiceEvenementiel,
                ],
                'choice_value' => static fn (?TypeFiche $type): ?string => $type?->value,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'placeholder' => 'Tous les statuts',
                'choices' => [
                    'En cours' => StatutFiche::EnCours,
                    'En attente' => StatutFiche::EnAttenteValidation,
                    'Publiée' => StatutFiche::Publiee,
                    'Archivée' => StatutFiche::Archivee,
                ],
                'choice_value' => static fn (?StatutFiche $status): ?string => $status?->value,
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'Pays',
                'required' => false,
                'placeholder' => 'Tous les pays',
                'choices' => GlobalSearchType::countryChoices($countries),
            ])
            ->add('completeness_min', IntegerType::class, [
                'label' => 'Complétude min (%)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
                'constraints' => [new Range(min: 0, max: 100)],
            ])
            ->add('completeness_max', IntegerType::class, [
                'label' => 'Complétude max (%)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
                'constraints' => [new Range(min: 0, max: 100)],
            ])
            ->add('limit', IntegerType::class, [
                'label' => 'Fiches par page',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 100],
                'constraints' => [new Range(min: 1, max: 100)],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Filtrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
            'countries' => [],
        ]);
        $resolver->setAllowedTypes('countries', 'string[]');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
