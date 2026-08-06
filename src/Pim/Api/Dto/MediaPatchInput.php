<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class MediaPatchInput
{
    public ?string $usage = null;
    public ?string $legende = null;
    public ?string $source = null;
    public ?string $keywords = null;
    public ?\DateTimeImmutable $rightsExpiresAt = null;
    public ?bool $rightsGranted = null;
    public ?string $salleId = null;
    /** @var array{x: int, y: int, width: int, height: int}|null */
    public ?array $crop = null;
    #[Assert\Choice(choices: [0, 90, 180, 270])]
    public ?int $rotation = null;
}
