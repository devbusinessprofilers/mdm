<?php
namespace App\Pim\Form\DataTransformer\ProviderPortal;

use App\Pim\Model\ProviderPortal\DTO\Localisation\NearPlacesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\PlaceChoiceDTO;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<NearPlaces, string>
 */
class NearPlacesValueTransformer implements DataTransformerInterface
{
    public function transform($value): ?NearPlacesDTO
    {
        if (!$value instanceof NearPlacesDTO) {
            return null;
        }

        $nearPlaces = new NearPlacesDTO();
        foreach ($value->placeChoices as $choice) {
            $nearPlaces->addPlaceChoice((string) $choice);
        }

        return $nearPlaces;
    }

    public function reverseTransform($value): ?NearPlacesDTO
    {
        if (!$value instanceof NearPlacesDTO) {
            return null;
        }

        $nearPlaces = new NearPlacesDTO();
        foreach ($value->placeChoices as $choice) {
            $decoded = json_decode($choice, true);
            if (!$decoded) {
                continue;
            }

            $placeLabel = $decoded['label'] ?? null;
            $placeId = $decoded['value'] ?? null;
            if (!$placeId) {
                continue;
            }

            $nearPlaces->addPlaceChoice((new PlaceChoiceDTO($placeId))->setLabel($placeLabel));
        }

        return $nearPlaces;
    }
}
