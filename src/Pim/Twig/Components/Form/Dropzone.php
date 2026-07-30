<?php

namespace App\Pim\Twig\Components\Form;

use App\Pim\Model\ProviderPortal\Form\Dropzone\Document;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Dropzone
{
    public string $id;

    public string $name;

    /**
     * @var array<Document>
     */
    public array $documents = [];

    public string $accept = '';

    public bool $disabled = false;

    public bool $withPreview = false;

    public int $maxFileCount = 5;

    /**
     * @see Symfony\Component\Validator\Constraints\File > maxSize
     */
    public string $fileMaxSize = '5M';

    public ?int $imageMinWidth = null;

    public ?int $imageMinHeight = null;

    public string $class = '';
}
