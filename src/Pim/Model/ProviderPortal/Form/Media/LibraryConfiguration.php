<?php

namespace App\Pim\Model\ProviderPortal\Form\Media;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\MediaCategoryChoices as ActivityPictureCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\MediaCategoryChoices as MealTrayPictureCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MediaCategoryChoices as PlacePictureCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\MediaCategoryChoices as RestaurantPictureCategories;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\MediaCategoryChoices as ServicePictureCategories;

class LibraryConfiguration
{
    /**
     * Translation key to display message on media (help, advice, recommendation...).
     */
    public ?string $help = null;

    /**
     * @var array<string, string>
     */
    public array $pictureCategories = [];

    /**
     * NOTE: for place medias only!
     * If specified value match selected picture category, additional field with place meeting rooms will be displayed.
     */
    public ?string $meetingRoomTriggerValue = null;

    public int $pictureMinWidth = 960;

    public int $pictureMinHeight = 480;

    public int $pictureMaxFileCount = 25;

    /**
     * NOTE: max file sie in MiB.
     */
    public int $pictureFileMaxSize = 25;

    public int $planMaxFileCount = 50;

    /**
     * NOTE: max file sie in MiB.
     */
    public int $planFileMaxSize = 10;

    public int $documentMaxFileCount = 100;

    /**
     * NOTE: max file sie in MiB.
     */
    public int $documentFileMaxSize = 5;

    public static function forActivity(): self
    {
        $config = new self();

        $config->pictureCategories = ActivityPictureCategories::getChoices();
        $config->help = 'form.sheet.activity.media.info';

        return $config;
    }

    public static function forMealTray(): self
    {
        $config = new self();

        $config->pictureCategories = MealTrayPictureCategories::getChoices();
        $config->help = 'form.sheet.mealTray.media.info';

        return $config;
    }

    public static function forPlace(): self
    {
        $config = new self();

        $config->pictureCategories = PlacePictureCategories::getChoices();
        $config->meetingRoomTriggerValue = 'salles-de-reunions';
        $config->help = 'form.sheet.place.media.info';

        return $config;
    }

    public static function forRestaurant(): self
    {
        $config = new self();

        $config->pictureCategories = RestaurantPictureCategories::getChoices();
        $config->help = 'form.sheet.restaurant.media.info';

        return $config;
    }

    public static function forService(): self
    {
        $config = new self();

        $config->pictureCategories = ServicePictureCategories::getChoices();
        $config->help = 'form.sheet.service.media.info';

        return $config;
    }
}
