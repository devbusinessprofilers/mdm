<?php

declare(strict_types=1);

namespace App\Shared\Form;

use App\Shared\Enum\TypeParametre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

/** @extends AbstractType<array<string, mixed>> */
final class ParametreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TypeParametre $type */
        $type = $options['type'];
        match ($type) {
            TypeParametre::Booleen => $builder->add('valeur', CheckboxType::class, [
                'label' => 'Activé', 'required' => false,
            ]),
            TypeParametre::Entier => $builder->add('valeur', IntegerType::class, [
                'label' => 'Valeur', 'constraints' => [new NotNull(), new GreaterThanOrEqual(0)],
            ]),
            TypeParametre::Texte => $builder->add('valeur', TextType::class, [
                'label' => 'Valeur', 'required' => false, 'empty_data' => '',
                'constraints' => [new Length(max: 255)],
            ]),
        };
        $builder->add('save', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
        $resolver->setRequired('type');
        $resolver->setAllowedTypes('type', TypeParametre::class);
    }
}
