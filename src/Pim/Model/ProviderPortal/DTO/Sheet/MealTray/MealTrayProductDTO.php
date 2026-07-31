<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray;

use App\Pim\Model\ProviderPortal\DTO\Sheet\VatPriceDTO;
use App\Pim\Model\ProviderPortal\Form\Dropzone\Document;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\AllergenTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\DelayLimitChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\DietaryPreferenceTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\DishTemperatureChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\MealTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\OrderLimitChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\TimeLimitChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\TypeChoices;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MealTrayProductDTO
{
    public ?string $name = null;

    public ?string $type = null;

    /**
     * @var array<UploadedFile>
     */
    public array $pictureFiles = [];

    /**
     * @var array<Document>
     */
    public array $pictureDocuments = [];

    public ?string $shortDescription = null;

    public ?string $longDescription = null;

    public ?string $dishTemperature = null;

    public ?VatPriceDTO $vatPrice = null;

    public ?int $capacity = null;

    public ?int $minOrderCount = null;

    public ?int $maxOrderCount = null;

    /**
     * @var array<string>
     */
    public array $dietaryPreferences = [];

    /**
     * @var array<string>
     */
    public array $allergens = [];

    /**
     * @var array<string>
     */
    public array $meals = [];

    public ?string $orderLimit = null;

    public ?string $timeLimit = null;

    public ?string $delayLimit = null;

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Lorem ipsum dolor sit amet consectetur sollicitudin.';
        $data->type = array_rand(array_flip(TypeChoices::getChoices()));

        $data->pictureDocuments = [Document::fromPath('/provider_portal/img/mock/picture.jpg')];

        $data->shortDescription = 'Lorem ipsum dolor sit amet consectetur. Arcu ornare feugiat dictumst id. Cursus est feugiat bibendum feugiat sit fames.';
        $data->longDescription = 'Lorem ipsum dolor sit amet consectetur. Arcu ornare feugiat dictumst id. Cursus est feugiat bibendum feugiat sit fames. Malesuada porttitor viverra faucibus tempor et. Tellus mauris arcu diam ut et vitae facilisi maecenas. Maecenas fermentum nulla nulla suscipit consequat. Arcu sed lobortis id blandit integer elit sed. Felis curabitur viverra purus vitae sollicitudin convallis ac. Bibendum nec tristique commodo venenatis diam cursus laoreet vitae.';
        $data->dishTemperature = array_rand(array_flip(DishTemperatureChoices::getChoices()));
        $data->vatPrice = VatPriceDTO::mock();
        $data->capacity = 20;
        $data->minOrderCount = 5;
        $data->maxOrderCount = 50;

        $data->dietaryPreferences = array_unique([
            DietaryPreferenceTagOptions::getTagOptions()[1]->value,
            DietaryPreferenceTagOptions::getTagOptions()[2]->value,
        ]);

        $data->allergens = array_unique([
            AllergenTagOptions::getTagOptions()[1]->value,
            AllergenTagOptions::getTagOptions()[2]->value,
        ]);

        $data->meals = array_unique([
            MealTagOptions::getTagOptions()[1]->value,
            MealTagOptions::getTagOptions()[2]->value,
        ]);

        $data->orderLimit = array_rand(array_flip(OrderLimitChoices::getChoices()));
        $data->timeLimit = array_rand(array_flip(TimeLimitChoices::getChoices()));
        $data->delayLimit = array_rand(array_flip(DelayLimitChoices::getChoices()));

        return $data;
    }
}
