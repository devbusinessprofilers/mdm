<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CompletenessSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setMethod('GET')
            ->add('type', ChoiceType::class, [
                'label' => 'Type', 'required' => false, 'placeholder' => 'Tous',
                'choices' => ['Lieu' => TypeFiche::Lieu, 'Activité' => TypeFiche::Activite, 'Restaurant' => TypeFiche::Restaurant, 'Service événementiel' => TypeFiche::ServiceEvenementiel],
                'choice_value' => static fn (?TypeFiche $type): ?string => $type?->value,
            ])->add('q', SearchType::class, ['label' => 'Recherche', 'required' => false])
            ->add('search', SubmitType::class, ['label' => 'Filtrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'csrf_protection' => false]);
    }
}
