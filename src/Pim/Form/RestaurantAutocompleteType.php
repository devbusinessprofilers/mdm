<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Repository\RestaurantRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Sélection d'un restaurant par recherche (liaison Lieu → Restaurant).
 *
 * @extends AbstractType<Restaurant>
 */
#[AsEntityAutocompleteField]
final class RestaurantAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Restaurant::class,
            'label' => 'Restaurant associé',
            'placeholder' => 'Rechercher un restaurant…',
            'choice_label' => static fn (
                Restaurant $restaurant,
            ): string => sprintf('%s — RES-%06d', $restaurant->label() ?: 'Restaurant sans nom', $restaurant->code()),
            'searchable_fields' => ['fiche.label', 'fiche.code'],
            'max_results' => 20,
            'security' => 'ROLE_BP_EDITOR',
            'query_builder' => static fn (RestaurantRepository $repository) => $repository->createAutocompleteQueryBuilder(),
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
