<?php

declare(strict_types=1);

namespace App\Audit\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class AuditHistoryFilterType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->setMethod('GET')
            ->add('action', TextType::class, ['required' => false])
            ->add('field', TextType::class, [
                'label' => 'Champ',
                'required' => false,
            ])
            ->add('actor', TextType::class, [
                'label' => 'Acteur',
                'required' => false,
            ])
            ->add('from', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('to', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('filter', SubmitType::class, ['label' => 'Filtrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
