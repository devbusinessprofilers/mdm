<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Service\ServiceAcces;
use App\Pim\Enum\TypeAccesService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ServiceAcces> */
final class ServiceAccesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => "Type d'accès",
                'choices' => TypeAccesService::choices(),
                'choice_value' => static fn (?TypeAccesService $type): ?string => $type?->value,
                'getter' => static fn (ServiceAcces $access): TypeAccesService => $access->type(),
                'setter' => static function (ServiceAcces &$access, TypeAccesService $value): void {
                    $access->changeType($value);
                },
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom ou indication',
                'getter' => static fn (ServiceAcces $access): string => $access->nom(),
                'setter' => static function (ServiceAcces &$access, ?string $value): void {
                    $access->changeNom((string) $value);
                },
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'required' => false,
                'getter' => static fn (ServiceAcces $access): int => $access->position(),
                'setter' => static function (ServiceAcces &$access, ?int $value): void {
                    $access->changePosition($value);
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ServiceAcces::class]);
    }
}
