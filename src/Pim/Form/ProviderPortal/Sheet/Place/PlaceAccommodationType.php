<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\SwitchButtonTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\SwitchButtonType;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceAccommodationDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EquipmentChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class PlaceAccommodationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('withAccommodation', SwitchButtonType::class, [
                'required' => false,
                SwitchButtonTypeAttributeEnum::INVERTED->value => true,
            ])
        ;

        $builder->addDependent('roomCount', 'withAccommodation', $this->shouldAddNumberType(...));
        $builder->addDependent('singleRoomCount', 'withAccommodation', fn (DependentField $field, bool $withAccomodation) => $this->shouldAddNumberType($field, $withAccomodation, false));
        $builder->addDependent('twinRoomCount', 'withAccommodation', fn (DependentField $field, bool $withAccomodation) => $this->shouldAddNumberType($field, $withAccomodation, false));
        $builder->addDependent('doubleRoomCount', 'withAccommodation', fn (DependentField $field, bool $withAccomodation) => $this->shouldAddNumberType($field, $withAccomodation, false));
        $builder->addDependent('totalCapacity', 'withAccommodation', $this->shouldAddNumberType(...));
        $builder->addDependent('description', 'withAccommodation', $this->shouldAddWysiwygType(...));
        $builder->addDependent('equipments', 'withAccommodation', $this->shouldAddEquipmentsType(...));
    }

    private function shouldAddNumberType(DependentField $field, bool $withAccommodation, bool $required = true): void
    {
        if (!$withAccommodation) {
            return;
        }

        $field->add(NumberType::class, [
            'required' => $required,
        ]);
    }

    private function shouldAddWysiwygType(DependentField $field, bool $withAccommodation): void
    {
        if (!$withAccommodation) {
            return;
        }

        $field->add(WysiwygType::class);
    }

    private function shouldAddEquipmentsType(DependentField $field, bool $withAccommodation): void
    {
        if (!$withAccommodation) {
            return;
        }

        $field->add(ChoiceType::class, [
            'multiple' => true,
            'choices' => EquipmentChoices::getChoices(),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceAccommodationDTO::class,
            'label_format' => 'form.sheet.place.accommodation.%name%.label',
        ]);
    }
}
