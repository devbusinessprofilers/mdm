<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Administration d'un site de diffusion. Le code est immuable après création,
 * comme celui des valeurs de LOV.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SiteDiffusionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'disabled' => $options['edition'],
                'constraints' => $options['edition'] ? [] : [
                    new NotBlank(message: 'Le code est obligatoire.'),
                    new Length(max: 64),
                    new Regex(pattern: '/^[A-Z0-9_]+$/', message: 'Majuscules, chiffres et tirets bas uniquement.'),
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'constraints' => [new NotBlank(message: 'Le libellé est obligatoire.'), new Length(max: 255)],
            ])
            ->add('groupe', TextType::class, [
                'label' => 'Groupe',
                'help' => 'Regroupement d\'affichage (ex. « Réseau Business Profilers », « Partenaires MICE »).',
                'constraints' => [new NotBlank(message: 'Le groupe est obligatoire.'), new Length(max: 128)],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'help' => 'Ordre d\'affichage global ; gardez les groupes contigus.',
            ])
            ->add('obligatoire', CheckboxType::class, [
                'label' => 'Obligatoire',
                'required' => false,
                'help' => 'Toujours retenu, non décochable dans les écrans.',
            ])
            ->add('payant', CheckboxType::class, [
                'label' => 'Payant',
                'required' => false,
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ])
            ->add('gammesParDefaut', ChoiceType::class, [
                'label' => 'Présélectionné à la création pour',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Lieux' => TypeFiche::Lieu,
                    'Restaurants' => TypeFiche::Restaurant,
                    'Activités' => TypeFiche::Activite,
                    'Services événementiels' => TypeFiche::ServiceEvenementiel,
                    'Plateaux repas' => TypeFiche::Traiteur,
                ],
                'choice_value' => static fn (?TypeFiche $type): ?string => $type?->value,
            ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'edition' => false,
        ]);
        $resolver->setAllowedTypes('edition', 'bool');
    }
}
