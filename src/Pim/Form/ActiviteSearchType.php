<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\StatutFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string,mixed>> */
final class ActiviteSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('q', SearchType::class, [
            'label' => 'Recherche',
            'required' => false,
            'attr' => ['placeholder' => 'Code, nom, localisation…'],
        ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'placeholder' => 'Tous',
                'choices' => [
                    'En cours' => StatutFiche::EnCours,
                    'En attente' => StatutFiche::EnAttenteValidation,
                    'Publiée' => StatutFiche::Publiee,
                    'Archivée' => StatutFiche::Archivee,
                ],
                'choice_value' => static fn (
                    ?StatutFiche $s,
                ): ?string => $s?->value,
            ])
            ->add('limit', IntegerType::class, [
                'label' => 'Résultats par page',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 100],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Rechercher']);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
