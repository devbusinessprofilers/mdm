<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Enum\TypeAccesRestaurant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<RestaurantAcces> */
final class RestaurantAccesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => "Type d'accès",
                'choices' => [
                    'Aéroport' => TypeAccesRestaurant::Aeroport,
                    'Gare' => TypeAccesRestaurant::Gare,
                    'Métro' => TypeAccesRestaurant::Metro,
                    'Tramway' => TypeAccesRestaurant::Tramway,
                    'Grande ville proche' => TypeAccesRestaurant::GrandeVille,
                ],
                'choice_value' => static fn (
                    ?TypeAccesRestaurant $type,
                ): ?string => $type?->value,
                'getter' => static fn (
                    RestaurantAcces $access,
                ): TypeAccesRestaurant => $access->type(),
                'setter' => static function (
                    RestaurantAcces &$access,
                    TypeAccesRestaurant $value,
                ): void {
                    $access->changeType($value);
                },
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom ou indication',
                'getter' => static fn (
                    RestaurantAcces $access,
                ): string => $access->nom(),
                'setter' => static function (
                    RestaurantAcces &$access,
                    string $value,
                ): void {
                    $access->changeNom($value);
                },
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'required' => false,
                'getter' => static fn (
                    RestaurantAcces $access,
                ): int => $access->position(),
                'setter' => static function (
                    RestaurantAcces &$access,
                    ?int $value,
                ): void {
                    $access->changePosition($value);
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RestaurantAcces::class]);
    }
}
