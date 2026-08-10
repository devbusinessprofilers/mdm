<?php

declare(strict_types=1);

namespace App\Account\Form;

use App\Pim\Entity\FicheCollaborateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/** @extends AbstractType<FicheCollaborateur> */
#[AsEntityAutocompleteField]
final class CollaborateurAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => FicheCollaborateur::class,
            'label' => 'Collaborateur existant',
            'placeholder' => 'Rechercher un collaborateur…',
            'choice_label' => static function (FicheCollaborateur $collaborateur): string {
                $name = trim($collaborateur->firstName().' '.$collaborateur->lastName());

                return '' === $name
                    ? $collaborateur->email()
                    : sprintf('%s — %s', $name, $collaborateur->email());
            },
            'searchable_fields' => ['email', 'firstName', 'lastName'],
            'max_results' => 20,
            'security' => 'ROLE_BP_EDITOR',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
