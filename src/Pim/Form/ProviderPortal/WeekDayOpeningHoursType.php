<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Model\ProviderPortal\DTO\Date\WeekDayOpeningHoursDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class WeekDayOpeningHoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('isOpen', SwitchButtonType::class, [
                'label' => $options['switch_label'],
            ])
        ;

        $builder->addDependent('from', 'isOpen', $this->isDayOpen(...));
        $builder->addDependent('to', 'isOpen', $this->isDayOpen(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['switch_label']);

        $resolver->setDefaults([
            'data_class' => WeekDayOpeningHoursDTO::class,
            'label_format' => 'form.week_day_opening_hours.%name%.label',
            'switch_label' => false,
            'required' => false,
        ]);
    }

    private function isDayOpen(DependentField $field, bool $isOpen): void
    {
        if (!$isOpen) {
            return;
        }

        $field->add(TextType::class, [
            'required' => false,
            'attr' => [
                'placeholder' => 'global.placeholder.time',
            ],
        ]);
    }
}
