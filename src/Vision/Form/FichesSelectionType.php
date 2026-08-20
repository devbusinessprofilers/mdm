<?php

declare(strict_types=1);

namespace App\Vision\Form;

use App\Pim\Entity\Fiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Sélection multiple de fiches pour lancer un traitement IA — le type
 * autocomplete du module Account est réservé aux super-admins, celui-ci est
 * ouvert aux éditeurs.
 *
 * @extends AbstractType<Fiche>
 */
#[AsEntityAutocompleteField]
final class FichesSelectionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Fiche::class,
            'label' => 'Fiches',
            'multiple' => true,
            'required' => true,
            'placeholder' => 'Rechercher des fiches…',
            'choice_label' => static function (Fiche $fiche): string {
                return sprintf('%d — %s', $fiche->code(), $fiche->label() ?? 'Sans libellé');
            },
            'searchable_fields' => ['label'],
            'max_results' => 20,
            'security' => 'ROLE_BP_EDITOR',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
