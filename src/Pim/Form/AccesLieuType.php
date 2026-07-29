<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Enum\TypeAccesLieu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<AccesLieu> */
final class AccesLieuType extends AbstractType
{
    /** @param FormBuilderInterface<AccesLieu|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->field($builder, 'type', ChoiceType::class, 'Type', [
            'choices' => array_combine(array_map(static fn (TypeAccesLieu $type): string => ucfirst(str_replace('_', ' ', $type->value)), TypeAccesLieu::cases()), TypeAccesLieu::cases()),
            'choice_value' => static fn (?TypeAccesLieu $type): ?string => $type?->value,
        ]);
        $this->field($builder, 'nom', TextType::class, 'Nom');
        $this->field($builder, 'distanceKilometres', NumberType::class, 'Distance (km)', ['required' => false, 'scale' => 2, 'input' => 'string']);
        $this->field($builder, 'dureeMinutes', IntegerType::class, 'Durée (minutes)', ['required' => false]);
        $this->field($builder, 'modeTransport', TextType::class, 'Mode de transport', ['required' => false]);
        $this->field($builder, 'position', IntegerType::class, 'Position', ['required' => false]);
    }

    /**
     * @param FormBuilderInterface<AccesLieu|null> $builder
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     */
    private function field(FormBuilderInterface $builder, string $name, string $type, string $label, array $options = []): void
    {
        $setter = 'change'.ucfirst($name);
        $builder->add($name, $type, $options + [
            'label' => $label,
            'getter' => static fn (AccesLieu $acces): mixed => $acces->{$name}(),
            'setter' => static function (AccesLieu &$acces, mixed $value) use ($setter): void { $acces->{$setter}($value); },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccesLieu::class]);
    }
}
