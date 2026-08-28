<?php

declare(strict_types=1);

namespace App\Shared\Form;

use App\Shared\Enum\TypeParametre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<array<string, mixed>> */
final class ParametreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TypeParametre $type */
        $type = $options['type'];
        /** @var array{min: int, max: int}|null $bornes */
        $bornes = $options['bornes'];
        match ($type) {
            TypeParametre::Booleen => $builder->add('valeur', CheckboxType::class, [
                'label' => 'Activé', 'required' => false,
            ]),
            TypeParametre::Entier => $builder->add('valeur', IntegerType::class, [
                'label' => 'Valeur',
                'help' => null === $bornes ? null : sprintf('Entre %d et %d.', $bornes['min'], $bornes['max']),
                'constraints' => [
                    new NotNull(),
                    null === $bornes
                        ? new GreaterThanOrEqual(0)
                        : new Range(min: $bornes['min'], max: $bornes['max']),
                ],
            ]),
            TypeParametre::Texte => $builder->add('valeur', TextType::class, [
                'label' => 'Valeur', 'required' => false, 'empty_data' => '',
                'constraints' => [new Length(max: 255)],
            ]),
            TypeParametre::TexteLong => $builder->add('valeur', TextareaType::class, [
                'label' => 'Valeur', 'required' => false, 'empty_data' => '',
                'attr' => ['rows' => 6],
                'constraints' => [new Length(max: 5000)],
            ]),
        };
        $builder->add('save', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'bornes' => null]);
        $resolver->setRequired('type');
        $resolver->setAllowedTypes('type', TypeParametre::class);
        $resolver->setAllowedTypes('bornes', ['null', 'array']);
    }
}
