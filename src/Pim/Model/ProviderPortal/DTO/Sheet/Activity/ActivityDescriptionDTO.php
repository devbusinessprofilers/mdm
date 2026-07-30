<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\PurposeChoices;

class ActivityDescriptionDTO
{
    public ?string $description = null;

    public ?string $content = null;

    /**
     * @var array<string>
     */
    public array $purposeList = [];

    public ?string $extra1 = null;

    public ?string $extra2 = null;

    public ?string $extra3 = null;

    public ?string $extra4 = null;

    public ?string $extra5 = null;

    public static function mock(): self
    {
        $data = new self();

        $data->description = 'Pellentesque lacinia dapibus tellus at tristique.';
        $data->content = 'Nullam in nisi bibendum, maximus libero id, venenatis erat';

        $data->purposeList = array_unique([
            array_rand(array_flip(PurposeChoices::getChoices())),
            array_rand(array_flip(PurposeChoices::getChoices())),
        ]);

        $data->extra1 = 'Belle vue';
        $data->extra2 = 'Au calme';

        return $data;
    }
}
