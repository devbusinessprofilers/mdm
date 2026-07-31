<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class NearPlacesDTO
{
    /** @var PlaceChoiceDTO[]|string[] DTO or Json encoded representation at format {label: string, placeId: string} */
    public array $placeChoices = [];

    public function addPlaceChoice(PlaceChoiceDTO|string $placeChoice): self
    {
        $this->placeChoices[] = $placeChoice;

        return $this;
    }
}
