<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Restaurant\RestaurantPeriodeFermeture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<RestaurantPeriodeFermeture> */
final class RestaurantPeriodeFermetureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la période',
                'getter' => static fn (
                    RestaurantPeriodeFermeture $period,
                ): string => $period->nom(),
                'setter' => static function (
                    RestaurantPeriodeFermeture &$period,
                    string $value,
                ): void {
                    $period->changeNom($value);
                },
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'getter' => static fn (
                    RestaurantPeriodeFermeture $period,
                ): ?\DateTimeImmutable => $period->dateDebut(),
                'setter' => static function (
                    RestaurantPeriodeFermeture &$period,
                    ?\DateTimeImmutable $value,
                ): void {
                    $period->changeDateDebut($value);
                },
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'getter' => static fn (
                    RestaurantPeriodeFermeture $period,
                ): ?\DateTimeImmutable => $period->dateFin(),
                'setter' => static function (
                    RestaurantPeriodeFermeture &$period,
                    ?\DateTimeImmutable $value,
                ): void {
                    $period->changeDateFin($value);
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantPeriodeFermeture::class,
        ]);
    }
}
