<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity;

use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\Price\ActivityOptionType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\Price\ActivityPackageType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityPriceDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class ActivityPriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('fromPrice', TextType::class)
            ->add('minCapacity', NumberType::class)
            ->add('maxCapacity', NumberType::class)
            ->add('withPackage1', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', 'false'],
            ])
            ->add('withPackage2', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', 'false'],
            ])
            ->add('withPackage3', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', 'false'],
            ])
            ->add('withOption1', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', 'false'],
            ])
            ->add('withOption2', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', 'false'],
            ])
            ->add('withOption3', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', 'false'],
            ])
        ;

        $builder->addDependent('package1', 'withPackage1', $this->withPackage(...));
        $builder->addDependent('package2', 'withPackage2', $this->withPackage(...));
        $builder->addDependent('package3', 'withPackage3', $this->withPackage(...));

        $builder->addDependent('option1', 'withOption1', $this->withOption(...));
        $builder->addDependent('option2', 'withOption2', $this->withOption(...));
        $builder->addDependent('option3', 'withOption3', $this->withOption(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityPriceDTO::class,
            'label_format' => 'form.sheet.activity.price.%name%.label',
        ]);
    }

    private function withPackage(DependentField $field, bool $withPackage): void
    {
        if (!$withPackage) {
            return;
        }

        $field->add(ActivityPackageType::class);
    }

    private function withOption(DependentField $field, bool $withOption): void
    {
        if (!$withOption) {
            return;
        }

        $field->add(ActivityOptionType::class);
    }
}
