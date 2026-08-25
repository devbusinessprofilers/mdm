<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\LieuRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Sélection d'un lieu par recherche (liaison Restaurant → Lieu).
 *
 * @extends AbstractType<Lieu>
 */
#[AsEntityAutocompleteField]
final class LieuAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Lieu::class,
            'label' => 'Lieu associé',
            'placeholder' => 'Rechercher un lieu…',
            'choice_label' => static fn (
                Lieu $lieu,
            ): string => sprintf('%s — LIE-%06d', $lieu->label() ?: 'Lieu sans nom', $lieu->code()),
            'searchable_fields' => ['fiche.label', 'fiche.code'],
            'max_results' => 20,
            'security' => 'ROLE_BP_EDITOR',
            'query_builder' => static fn (LieuRepository $repository) => $repository->createAutocompleteQueryBuilder(),
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
