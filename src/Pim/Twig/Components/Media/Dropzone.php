<?php

namespace App\Pim\Twig\Components\Media;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Dropzone
{
    public string $identifier;

    /**
     * @var string (picture|document)
     */
    public string $previewType;

    public int $maxFileCount;

    /**
     * NOTE: max file sie in MiB.
     */
    public int $fileMaxSize;

    public ?int $imageMinWidth = null;

    public ?int $imageMinHeight = null;
}
