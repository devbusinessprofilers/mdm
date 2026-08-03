<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Enrichment\Enum\SupportedLocale;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/** @extends AbstractType<array<string, mixed>> */
final class LovValueAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'Code technique', 'disabled' => !$options['creation'],
            'constraints' => [new NotBlank(), new Regex('/^[A-Z0-9][A-Z0-9_-]{1,95}$/')],
        ])->add('label', TextType::class, ['label' => 'Libellé français', 'constraints' => [new NotBlank()]])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('active', CheckboxType::class, ['label' => 'Valeur active', 'required' => false]);
        foreach (SupportedLocale::targets() as $locale) {
            $builder->add('translation_'.$locale->value, TextType::class, ['label' => $locale->label(), 'required' => false]);
        }
        $builder->add('save', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'creation' => false]);
        $resolver->setAllowedTypes('creation', 'bool');
    }
}
