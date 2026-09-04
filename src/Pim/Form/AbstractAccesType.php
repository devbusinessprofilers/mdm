<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ligne du bloc Accès, commune aux gammes (Lieu, Restaurant, Service) :
 * type (énumération propre à la gamme), nom, distance, durée, mode de
 * transport, position. Chaque gamme ne fournit que sa classe d'accès et ses
 * types (libellé => cas d'énumération).
 *
 * @template TAcces of object
 *
 * @extends AbstractType<TAcces>
 */
abstract class AbstractAccesType extends AbstractType
{
    /** @return class-string<TAcces> */
    abstract protected function classeAcces(): string;

    /** @return array<string, \BackedEnum> libellé => type d'accès */
    abstract protected function typesAcces(): array;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->field($builder, 'type', ChoiceType::class, "Type d'accès", [
            'choices' => $this->typesAcces(),
            'choice_value' => static fn (?\BackedEnum $type): ?string => null === $type ? null : (string) $type->value,
        ]);
        $this->field($builder, 'nom', TextType::class, 'Nom ou indication');
        $this->field($builder, 'distanceKilometres', NumberType::class, 'Distance (km)', ['required' => false, 'scale' => 2, 'input' => 'string']);
        $this->field($builder, 'dureeMinutes', IntegerType::class, 'Durée (minutes)', ['required' => false]);
        $this->field($builder, 'modeTransport', TextType::class, 'Mode de transport', ['required' => false]);
        $this->field($builder, 'position', IntegerType::class, 'Position', ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => $this->classeAcces()]);
    }

    /**
     * @param FormBuilderInterface<mixed>              $builder
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed>                     $options
     */
    private function field(FormBuilderInterface $builder, string $name, string $type, string $label, array $options = []): void
    {
        $setter = 'change'.ucfirst($name);
        $builder->add($name, $type, $options + [
            'label' => $label,
            'getter' => static fn (object $acces): mixed => $acces->{$name}(),
            'setter' => static function (object &$acces, mixed $value) use ($setter): void { $acces->{$setter}($value); },
        ]);
    }
}
