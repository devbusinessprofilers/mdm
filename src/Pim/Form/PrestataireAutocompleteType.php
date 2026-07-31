<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\ValeurAttribut;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/** @extends AbstractType<ValeurAttribut> */
#[AsEntityAutocompleteField]
final class PrestataireAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => ValeurAttribut::class,
            'label' => 'Prestataire',
            'placeholder' => 'Rechercher un prestataire…',
            'choice_label' => static fn (
                ValeurAttribut $v,
            ): string => $v->label(),
            'searchable_fields' => ['label', 'code'],
            'max_results' => 20,
            'security' => 'ROLE_BP_EDITOR',
            'query_builder' => static fn (EntityRepository $r) => $r
                ->createQueryBuilder('v')
                ->innerJoin('v.attribute', 'a')
                ->andWhere('a.code = :code')
                ->setParameter('code', 'PRESTATAIRE')
                ->orderBy('v.label', 'ASC'),
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
