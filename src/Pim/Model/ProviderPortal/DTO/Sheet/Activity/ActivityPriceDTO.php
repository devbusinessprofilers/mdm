<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\Price\ActivityOptionDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\Price\ActivityPackageDTO;

class ActivityPriceDTO
{
    public float $fromPrice = 0;

    public int $minCapacity = 0;

    public int $maxCapacity = 0;

    public bool $withPackage1 = false;

    public bool $withPackage2 = false;

    public bool $withPackage3 = false;

    public ?ActivityPackageDTO $package1 = null;

    public ?ActivityPackageDTO $package2 = null;

    public ?ActivityPackageDTO $package3 = null;

    public bool $withOption1 = false;

    public bool $withOption2 = false;

    public bool $withOption3 = false;

    public ?ActivityOptionDTO $option1 = null;

    public ?ActivityOptionDTO $option2 = null;

    public ?ActivityOptionDTO $option3 = null;

    public static function mock(): self
    {
        $data = new self();

        $data->fromPrice = 100;
        $data->minCapacity = 5;
        $data->maxCapacity = 100;

        $data->withPackage1 = true;
        $data->package1 = ActivityPackageDTO::mock();

        $data->withOption1 = true;
        $data->option1 = ActivityOptionDTO::mock();

        return $data;
    }
}
