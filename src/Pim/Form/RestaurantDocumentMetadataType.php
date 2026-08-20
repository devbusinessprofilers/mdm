<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Restaurant\RestaurantSalle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Métadonnées d'un document Restaurant : celles des gammes, plus la salle
 * rattachée — corrigeable depuis la modale du bloc médias, comme le Lieu.
 *
 * @extends AbstractType<array<string,mixed>>
 */
final class RestaurantDocumentMetadataType extends AbstractType
{
    public function getParent(): string
    {
        return ActiviteDocumentMetadataType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('salle', EntityType::class, [
            'class' => RestaurantSalle::class,
            'choices' => $options['salles'],
            'choice_label' => static fn (RestaurantSalle $salle): string => $salle->nom(),
            'label' => 'Salle rattachée',
            'placeholder' => 'Aucune',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['salles' => []]);
        $resolver->setAllowedTypes('salles', 'array');
    }
}
