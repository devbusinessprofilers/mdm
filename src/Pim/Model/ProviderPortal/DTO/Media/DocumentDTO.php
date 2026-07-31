<?php

namespace App\Pim\Model\ProviderPortal\DTO\Media;

/**
 * Common DTO for media document.
 */
class DocumentDTO
{
    public ?string $id = null;

    public ?string $fileName = null;

    public int $rank = 0;

    public static function mock(int $rank = 0): self
    {
        $data = new self();

        $data->id = uniqid('media_');
        $data->fileName = 'document.pdf';
        $data->rank = $rank;

        return $data;
    }
}
