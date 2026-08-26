<?php

declare(strict_types=1);

namespace App\Account\Form;

use App\Pim\Entity\Fiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/** @extends AbstractType<Fiche> */
#[AsEntityAutocompleteField]
final class FicheAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Fiche::class,
            'label' => 'Fiche',
            'placeholder' => 'Rechercher une fiche…',
            'choice_label' => static function (Fiche $fiche): string {
                $label = $fiche->label() ?? 'Sans libellé';

                return sprintf('%d — %s', $fiche->code(), $label);
            },
            'searchable_fields' => ['label'],
            'max_results' => 20,
            'security' => 'ROLE_BP_VALIDATOR',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
