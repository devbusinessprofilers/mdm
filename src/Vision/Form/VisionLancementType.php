<?php

declare(strict_types=1);

namespace App\Vision\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lancement d'un traitement IA sur une sélection de fiches : autocomplete
 * multiple + bouton, rendu via le composant Form.
 *
 * @extends AbstractType<array{fiches: list<\App\Pim\Entity\Fiche>}>
 */
final class VisionLancementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fiches', FichesSelectionType::class)
            ->add('save', SubmitType::class, ['label' => $options['button_label']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'POST',
        ]);
        $resolver->setRequired('button_label');
        $resolver->setAllowedTypes('button_label', 'string');
    }
}
