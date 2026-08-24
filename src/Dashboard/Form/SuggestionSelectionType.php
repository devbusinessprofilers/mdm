<?php

declare(strict_types=1);

namespace App\Dashboard\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Sélection et arbitrage groupé du tableau de suggestions de l'écran Qualité :
 * les cases des lignes de la page (ids). La décision (Accepter / Ignorer) est
 * portée par l'URL du bouton (route {decision}). Chaque id est revalidé côté
 * contrôleur.
 *
 * @extends AbstractType<array{ids: list<string>}>
 */
final class SuggestionSelectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ids', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['ids_choices'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'POST',
            'csrf_token_id' => 'suggestion-selection',
            'ids_choices' => [],
        ]);
        $resolver->setAllowedTypes('ids_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'suggestion_selection';
    }
}
