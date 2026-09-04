<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Question Oui / Non de la maquette portail : deux boutons radio, « Non »
 * coché par défaut. Un champ jamais renseigné (null en base) s'affiche et
 * s'enregistre comme « Non » — il n'y a plus d'état « Non renseigné », donc
 * plus de règle « une réponse Oui/Non est requise » à la soumission.
 *
 * Rendu par le bloc choice_widget_expanded opt-in `data-oui-non` du thème
 * de l'éditeur (pim/form/_form-theme-fiche).
 *
 * @extends AbstractType<bool|null>
 */
final class OuiNonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?bool $value): bool => true === $value,
            static fn (mixed $value): bool => true === $value || '1' === $value || 1 === $value,
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr'] = ['data-oui-non' => true] + $view->vars['attr'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => ['Oui' => true, 'Non' => false],
            'choice_value' => static fn (?bool $value): string => true === $value ? '1' : '0',
            'expanded' => true,
            'multiple' => false,
            'placeholder' => false,
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'oui_non';
    }
}
