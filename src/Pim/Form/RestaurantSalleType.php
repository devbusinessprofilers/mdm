<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Restaurant\RestaurantSalle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<RestaurantSalle> */
final class RestaurantSalleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->field($builder, 'nom', TextType::class, 'Nom', true);

        foreach (
            [
                'superficie' => 'Superficie (m²)',
                'capaciteReunion' => 'Capacité réunion',
                'capaciteU' => 'Capacité en U',
                'capaciteClasse' => 'Capacité classe',
                'capaciteTheatre' => 'Capacité théâtre',
                'capaciteCabaret' => 'Capacité cabaret',
                'capaciteBanquet' => 'Capacité banquet',
                'capaciteCocktail' => 'Capacité cocktail',
                'capaciteAuditorium' => 'Capacité auditorium',
                'position' => 'Position',
            ] as $name => $label
        ) {
            $this->field($builder, $name, IntegerType::class, $label);
        }

        foreach (
            [
                'lumiereJour' => 'Lumière du jour',
                'accesPmr' => 'Accès PMR',
                'espaceDansant' => 'Espace dansant',
                'climatisee' => 'Climatisée',
            ] as $name => $label
        ) {
            $this->field($builder, $name, CheckboxType::class, $label);
        }
    }

    /**
     * @param FormBuilderInterface<RestaurantSalle|null> $builder
     * @param class-string<FormTypeInterface<mixed>>     $type
     */
    private function field(
        FormBuilderInterface $builder,
        string $name,
        string $type,
        string $label,
        bool $required = false,
    ): void {
        $setter = 'change'.ucfirst($name);
        $builder->add($name, $type, [
            'label' => $label,
            'required' => $required,
            'getter' => static fn (
                RestaurantSalle $room,
            ): mixed => $room->{$name}(),
            'setter' => static function (
                RestaurantSalle &$room,
                mixed $value,
            ) use ($setter): void {
                $room->{$setter}($value);
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RestaurantSalle::class]);
    }
}
