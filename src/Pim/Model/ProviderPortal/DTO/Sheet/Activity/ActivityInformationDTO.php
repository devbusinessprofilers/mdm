<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\AquaticTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\ArtisticTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\CulinaryTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\CulturalTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\DigitalTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\ExtremeTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\FunTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\LanguageChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\NaturalTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\ThematicChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\TypeChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\WellnessTagOptions;

class ActivityInformationDTO
{
    public ?string $name = null;

    /**
     * @var array<string>
     */
    public array $types = [];

    /**
     * @var array<string>
     */
    public array $languages = [];

    /**
     * @var array<string>
     */
    public ?array $thematic = null;

    /**
     * @var array<string>
     */
    public array $aquaticList = [];

    /**
     * @var array<string>
     */
    public array $artisticList = [];

    /**
     * @var array<string>
     */
    public array $culinaryList = [];

    /**
     * @var array<string>
     */
    public array $culturalList = [];

    /**
     * @var array<string>
     */
    public array $digitalList = [];

    /**
     * @var array<string>
     */
    public array $extremeList = [];

    /**
     * @var array<string>
     */
    public array $funList = [];

    /**
     * @var array<string>
     */
    public array $naturalList = [];

    /**
     * @var array<string>
     */
    public array $wellnessList = [];

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Proin porttitor';

        $data->types = array_unique([
            array_rand(array_flip(TypeChoices::getChoices())),
            array_rand(array_flip(TypeChoices::getChoices())),
        ]);

        $data->languages = array_unique([
            array_rand(array_flip(LanguageChoices::getChoices())),
            array_rand(array_flip(LanguageChoices::getChoices())),
        ]);

        $data->thematic = [
            array_rand(array_flip(ThematicChoices::getChoices())),
        ];

        $data->aquaticList = [
            AquaticTagOptions::getTagOptions()[1]->value,
            AquaticTagOptions::getTagOptions()[2]->value,
        ];

        $data->artisticList = [
            ArtisticTagOptions::getTagOptions()[1]->value,
            ArtisticTagOptions::getTagOptions()[2]->value,
        ];

        $data->culinaryList = [
            CulinaryTagOptions::getTagOptions()[1]->value,
            CulinaryTagOptions::getTagOptions()[2]->value,
        ];

        $data->culturalList = [
            CulturalTagOptions::getTagOptions()[1]->value,
            CulturalTagOptions::getTagOptions()[2]->value,
        ];

        $data->digitalList = [
            DigitalTagOptions::getTagOptions()[1]->value,
            DigitalTagOptions::getTagOptions()[2]->value,
        ];

        $data->extremeList = [
            ExtremeTagOptions::getTagOptions()[1]->value,
            ExtremeTagOptions::getTagOptions()[2]->value,
        ];

        $data->funList = [
            FunTagOptions::getTagOptions()[1]->value,
            FunTagOptions::getTagOptions()[2]->value,
        ];

        $data->naturalList = [
            NaturalTagOptions::getTagOptions()[1]->value,
            NaturalTagOptions::getTagOptions()[2]->value,
        ];

        $data->wellnessList = [
            WellnessTagOptions::getTagOptions()[1]->value,
            WellnessTagOptions::getTagOptions()[2]->value,
        ];

        return $data;
    }
}
