<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray;

class MealTrayDescriptionDTO
{
    public ?string $description = null;

    public ?string $cuisine = null;

    /**
     * @var array<string>
     */
    public array $csrCommitments = [];

    public static function mock(): self
    {
        $data = new self();

        return $data;
    }
}
