<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Service;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\ActivityChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\CommunicationChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\DigitalChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\FacilityChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\FoodChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\GiftChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\MarketingChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\MiscellaneousChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\ReceptionChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\TranslationChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\TransportChoices;

class ServiceTypologyDTO
{
    /**
     * @var array<string>
     */
    public array $receptionList = [];

    /**
     * @var array<string>
     */
    public array $giftList = [];

    /**
     * @var array<string>
     */
    public array $communicationList = [];

    /**
     * @var array<string>
     */
    public array $facilityList = [];

    /**
     * @var array<string>
     */
    public array $digitalList = [];

    /**
     * @var array<string>
     */
    public array $activityList = [];

    /**
     * @var array<string>
     */
    public array $translationList = [];

    /**
     * @var array<string>
     */
    public array $transportList = [];

    /**
     * @var array<string>
     */
    public array $foodList = [];

    /**
     * @var array<string>
     */
    public array $miscellaneousList = [];

    /**
     * @var array<string>
     */
    public array $marketingList = [];

    public static function mock(): self
    {
        $data = new self();

        $data->receptionList = array_unique([
            array_rand(array_flip(ReceptionChoices::getChoices())),
            array_rand(array_flip(ReceptionChoices::getChoices())),
        ]);
        $data->giftList = array_unique([
            array_rand(array_flip(GiftChoices::getChoices())),
            array_rand(array_flip(GiftChoices::getChoices())),
        ]);
        $data->communicationList = array_unique([
            array_rand(array_flip(CommunicationChoices::getChoices())),
            array_rand(array_flip(CommunicationChoices::getChoices())),
        ]);
        $data->facilityList = array_unique([
            array_rand(array_flip(FacilityChoices::getChoices())),
            array_rand(array_flip(FacilityChoices::getChoices())),
        ]);
        $data->digitalList = array_unique([
            array_rand(array_flip(DigitalChoices::getChoices())),
            array_rand(array_flip(DigitalChoices::getChoices())),
        ]);
        $data->activityList = array_unique([
            array_rand(array_flip(ActivityChoices::getChoices())),
            array_rand(array_flip(ActivityChoices::getChoices())),
        ]);
        $data->translationList = array_unique([
            array_rand(array_flip(TranslationChoices::getChoices())),
            array_rand(array_flip(TranslationChoices::getChoices())),
        ]);
        $data->transportList = array_unique([
            array_rand(array_flip(TransportChoices::getChoices())),
            array_rand(array_flip(TransportChoices::getChoices())),
        ]);
        $data->foodList = array_unique([
            array_rand(array_flip(FoodChoices::getChoices())),
            array_rand(array_flip(FoodChoices::getChoices())),
        ]);
        $data->miscellaneousList = array_unique([
            array_rand(array_flip(MiscellaneousChoices::getChoices())),
            array_rand(array_flip(MiscellaneousChoices::getChoices())),
        ]);
        $data->marketingList = array_unique([
            array_rand(array_flip(MarketingChoices::getChoices())),
            array_rand(array_flip(MarketingChoices::getChoices())),
        ]);

        return $data;
    }
}
