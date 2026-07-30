<?php

namespace App\Pim\Model\ProviderPortal\DTO\Media;

/**
 * Common DTO for media library.
 */
class LibraryDTO
{
    /**
     * @var array<PictureDTO>
     */
    public array $pictures = [];

    /**
     * @var array<DocumentDTO>
     */
    public array $plans = [];

    /**
     * @var array<DocumentDTO>
     */
    public array $documents = [];

    public ?string $videoLink = null;

    public bool $optIn = false;

    public static function mock(string $sheet = 'place'): self
    {
        $data = new self();

        $data->pictures = [
            PictureDTO::mock($sheet, 1),
            PictureDTO::mock($sheet, 2),
            PictureDTO::mock($sheet, 3),
            PictureDTO::mock($sheet, 4),
            PictureDTO::mock($sheet, 5),
            PictureDTO::mock($sheet, 6),
        ];

        $data->documents = [
            DocumentDTO::mock(1),
            DocumentDTO::mock(2),
            DocumentDTO::mock(3),
            DocumentDTO::mock(4),
            DocumentDTO::mock(5),
        ];

        return $data;
    }
}
