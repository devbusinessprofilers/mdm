<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\LabelAttributeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Model\ProviderPortal\DTO\Date\OpeningHoursDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpeningHoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('monday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.monday',
            ])
            ->add('tuesday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.tuesday',
            ])
            ->add('wednesday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.wednesday',
            ])
            ->add('thursday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.thursday',
            ])
            ->add('friday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.friday',
            ])
            ->add('saturday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.saturday',
            ])
            ->add('sunday', WeekDayOpeningHoursType::class, [
                'label' => false,
                'switch_label' => 'global.week_day.sunday',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OpeningHoursDTO::class,
            'label_attr' => [
                LabelAttributeEnum::TYPOGRAPHY->value => TypographyVariantEnum::SUBTITLE->value,
                LabelAttributeEnum::BOLD->value => true,
            ],
        ]);
    }
}
