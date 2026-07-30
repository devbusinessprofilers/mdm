<?php

namespace App\Pim\Model\ProviderPortal\DTO\Media;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\Meeting\MeetingRoomDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceMeetingDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\MediaCategoryChoices as ActivityMediaCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\MediaCategoryChoices as MealTrayMediaCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MediaCategoryChoices as PlaceMediaCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\MediaCategoryChoices as RestaurantMediaCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\MediaCategoryChoices as ServiceMediaCategories;

/**
 * Common DTO for media image.
 *
 * @phpstan-type _CropDataType array{
 *      matrix: array<float>,
 *      selection: array{x: int, y: int, width: int, height: int},
 *      naturalWidth: int,
 *      naturalHeight: int,
 *      zoom: float,
 *  }
 */
class PictureDTO
{
    public ?string $id = null;

    /**
     * Category depending on sheet type (place, activity...).
     */
    public ?string $category = null;

    /**
     * @var _CropDataType
     */
    public array $crop = [];

    /**
     * NOTE: for place medias only!
     *
     * @see PlaceMeetingDTO > meetingRooms
     */
    public ?MeetingRoomDTO $meetingRoom = null;

    public int $rank = 0;

    public static function mock(string $sheet = 'place', int $rank = 0): self
    {
        $data = new self();

        // NOTE: file can be added in 'public/downloads' directory with name 'media_XXXXXX.jpg'
        $data->id = 'media_'.str_pad($rank, 6, '0', STR_PAD_LEFT).'.jpg';
        $data->category = match ($sheet) {
            'activity' => array_rand(array_flip(ActivityMediaCategories::getChoices())),
            'mealTray' => array_rand(array_flip(MealTrayMediaCategories::getChoices())),
            'place' => array_rand(array_flip(PlaceMediaCategories::getChoices())),
            'restaurant' => array_rand(array_flip(RestaurantMediaCategories::getChoices())),
            'service' => array_rand(array_flip(ServiceMediaCategories::getChoices())),
        };
        $data->rank = $rank;

        return $data;
    }
}
