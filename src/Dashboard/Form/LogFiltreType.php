<?php

declare(strict_types=1);

namespace App\Dashboard\Form;

use App\Dashboard\Model\LogFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Filtres de la visionneuse de logs (/admin/performance). Formulaire GET sans
 * CSRF ni préfixe (créé via createNamed('')) : les clés d'URL restent celles
 * que LogFilter::fromRequest lit (niveau, canal, q, depuis, jusqua).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class LogFiltreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('niveau', ChoiceType::class, [
                'label' => 'Niveau',
                'required' => false,
                'choices' => array_flip(LogFilter::NIVEAUX),
                'placeholder' => false,
            ])
            ->add('canal', ChoiceType::class, [
                'label' => 'Canal',
                'required' => false,
                'choices' => array_combine($options['canaux'], $options['canaux']),
                'placeholder' => 'Tous les canaux',
            ])
            ->add('q', SearchType::class, [
                'label' => 'Message contient',
                'required' => false,
                'attr' => ['placeholder' => 'texte du message…'],
            ])
            ->add('depuis', DateTimeType::class, [
                'label' => 'Depuis',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('jusqua', DateTimeType::class, [
                'label' => 'Jusqu’à',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('filtrer', SubmitType::class, ['label' => 'Filtrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true, // page, fenetre…
            'canaux' => [],
        ]);
        $resolver->setAllowedTypes('canaux', 'array');
    }
}
