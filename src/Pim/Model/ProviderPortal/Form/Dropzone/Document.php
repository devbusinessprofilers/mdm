<?php

namespace App\Pim\Model\ProviderPortal\Form\Dropzone;

class Document
{
    public function __construct(
        public string $name,
        public string $url,
    ) {
    }

    public static function fromPath(string $filePath): self
    {
        return new self(basename($filePath), $filePath);
    }
}
