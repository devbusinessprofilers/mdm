<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

final class DocumentPatchInput
{
    public ?string $usage = null;
    public ?string $title = null;
    public ?string $source = null;
    public ?bool $rightsGranted = null;
    public ?string $salleId = null;
}
