<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<array<string, mixed>> */
final class GlobalSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $countries */
        $countries = $options['countries'];
        $builder
            ->add('q', SearchType::class, [
                'label' => 'Recherche',
                'required' => false,
                'attr' => ['placeholder' => 'Code, libellé, ville…'],
            ])
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
                    'Validée' => StatutFiche::Validee,
                    'Publiée' => StatutFiche::Publiee,
                    'Archivée' => StatutFiche::Archivee,
                ],
                'choice_value' => static fn (?StatutFiche $status): ?string => $status?->value,
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'Pays',
                'required' => false,
                'placeholder' => 'Tous les pays',
                'choices' => self::countryChoices($countries),
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
                'label' => 'Résultats par page',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 100],
                'constraints' => [new Range(min: 1, max: 100)],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Rechercher']);
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

    /**
     * @param list<string> $codes
     *
     * @return array<string, string>
     */
    public static function countryChoices(array $codes): array
    {
        $choices = [];
        foreach ($codes as $code) {
            $label = Countries::exists($code) ? Countries::getName($code, 'fr') : $code;
            $choices[$label] = $code;
        }
        ksort($choices);

        return $choices;
    }
}
