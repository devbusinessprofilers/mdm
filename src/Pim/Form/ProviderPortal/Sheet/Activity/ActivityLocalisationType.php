<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity;

use App\Pim\Enum\ProviderPortal\GeographicalRangeEnum;
use App\Pim\Form\ProviderPortal\AddressType;
use App\Pim\Form\ProviderPortal\EnumType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityLocalisationDTO;
use App\Pim\Model\ProviderPortal\Mock\DepartmentChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class ActivityLocalisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('geographicRange', EnumType::class, [
                'label' => false,
                'expanded' => true,
                'enum' => GeographicalRangeEnum::class,
            ])
        ;

        $builder->addDependent('address', 'geographicRange', $this->handleFixGeographicRange(...));
        $builder->addDependent(
            'countries',
            'geographicRange',
            fn (DependentField $field, ?GeographicalRangeEnum $geographicRange) => $this->handleMobileGeographicRange(
                $field,
                $geographicRange,
                fn () => array_flip(Countries::getNames()),
            )
        );
        $builder->addDependent(
            'departments',
            'geographicRange',
            fn (DependentField $field, ?GeographicalRangeEnum $geographicRange) => $this->handleMobileGeographicRange(
                $field,
                $geographicRange,
                DepartmentChoices::getChoices(...),
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityLocalisationDTO::class,
            'label_format' => 'form.sheet.activity.localisation.%name%.label',
        ]);
    }

    private function handleFixGeographicRange(DependentField $field, ?GeographicalRangeEnum $geographicRange): void
    {
        if (GeographicalRangeEnum::FIX_ADDRESS !== $geographicRange) {
            return;
        }

        $field->add(AddressType::class);
    }

    private function handleMobileGeographicRange(DependentField $field, ?GeographicalRangeEnum $geographicRange, \Closure $getChoices): void
    {
        if (GeographicalRangeEnum::MOBILE !== $geographicRange) {
            return;
        }

        $field->add(ChoiceType::class, [
            'multiple' => true,
            'choices' => $getChoices(),
        ]);
    }
}
