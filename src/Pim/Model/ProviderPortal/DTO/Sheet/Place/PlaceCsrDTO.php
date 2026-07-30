<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\DistinctionChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EnvironmentalImpactChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MobilityChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\PurchaseCategoryChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\PurchaseChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\SocialImpactChoices;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PlaceCsrDTO
{
    public ?string $commitmentUrl = null;

    public ?UploadedFile $commitmentFile = null;

    /**
     * @var array<string>
     */
    public ?array $purchase = null;

    /**
     * @var array<string>
     */
    public ?array $environmentalImpact = null;

    /**
     * @var array<string>
     */
    public ?array $socialImpact = null;

    /**
     * @var array<string>
     */
    public ?array $purchaseCategory = null;

    /**
     * @var array<string>
     */
    public ?array $mobility = null;

    /**
     * @var array<string>
     */
    public ?array $distinction = null;

    /**
     * @var array<string>
     */
    public static function mock(): self
    {
        $data = new self();

        $data->purchase = [array_rand(array_flip(PurchaseChoices::getChoices()))];
        $data->environmentalImpact = [array_rand(array_flip(EnvironmentalImpactChoices::getChoices()))];
        $data->socialImpact = [array_rand(array_flip(SocialImpactChoices::getChoices()))];
        $data->purchaseCategory = [array_rand(array_flip(PurchaseCategoryChoices::getChoices()))];
        $data->mobility = [array_rand(array_flip(MobilityChoices::getChoices()))];
        $data->distinction = [array_rand(array_flip(DistinctionChoices::getChoices()))];

        return $data;
    }
}
