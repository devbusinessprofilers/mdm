<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Localisation\NearPlaceTypeEnum;
use App\Pim\Form\ProviderPortal\AddressType;
use App\Pim\Form\ProviderPortal\NearPlacesType;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceLocalisationDTO;
use App\Pim\Service\Localisation\NearbyPlaceClientInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class PlaceLocalisationType extends AbstractType
{
    public function __construct(
        private readonly NearbyPlaceClientInterface $nearbyPlaceClient,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('address', AddressType::class)
            ->add('hasReducedMobilityAccess', YesNoType::class)
        ;

        $builder->addDependent(
            'nearTrainStations',
            'address',
            fn (DependentField $field, ?AddressDTO $address) => $this->isPositionSet($field, $address, NearPlaceTypeEnum::TRAIN_STATION)
        );
        $builder->addDependent(
            'nearSubwayStations',
            'address',
            fn (DependentField $field, ?AddressDTO $address) => $this->isPositionSet($field, $address, NearPlaceTypeEnum::SUBWAY_STATION)
        );
        $builder->addDependent(
            'nearLightRailStations',
            'address',
            fn (DependentField $field, ?AddressDTO $address) => $this->isPositionSet($field, $address, NearPlaceTypeEnum::LIGHT_RAIL_STATION)
        );
        $builder->addDependent(
            'nearAirports',
            'address',
            fn (DependentField $field, ?AddressDTO $address) => $this->isPositionSet($field, $address, NearPlaceTypeEnum::AIRPORT)
        );
        $builder->addDependent(
            'nearCities',
            'address',
            fn (DependentField $field, ?AddressDTO $address) => $this->isPositionSet($field, $address, NearPlaceTypeEnum::CITY)
        );

        $builder->addDependent('reducedMobilityAccessDescription', 'hasReducedMobilityAccess', $this->isReducedMobilityAccess(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceLocalisationDTO::class,
            'label_format' => 'form.sheet.place.localisation.%name%.label',
        ]);
    }

    private function isPositionSet(DependentField $field, ?AddressDTO $address, NearPlaceTypeEnum $type): void
    {
        if (
            !$address
            || !$address->position
            || !$address->position->latitude
            || !$address->position->longitude
        ) {
            return;
        }

        $position = new CoordinatesDTO($address->position->latitude, $address->position->longitude);

        $field->add(NearPlacesType::class, [
            'type' => $type,
            'position' => $position,
        ]);
    }

    private function isReducedMobilityAccess(DependentField $field, ?bool $hasReducedMobilityAccess): void
    {
        if (!$hasReducedMobilityAccess) {
            return;
        }

        $field->add(WysiwygType::class, [
            'label' => false,
        ]);
    }
}
