<?php

declare(strict_types=1);

namespace App\Enrichment\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<array{fieldPath: string, value: string}> */
final class TranslationCorrectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fieldPath', HiddenType::class)
        ->add('value', TextareaType::class, [
            'label' => 'Traduction',
            'constraints' => [new NotBlank(), new Length(max: 65535)],
        ])->add('save', SubmitType::class, ['label' => 'Enregistrer la correction']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
