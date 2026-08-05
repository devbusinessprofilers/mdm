<?php

declare(strict_types=1);

namespace App\Ocr\Catalog;

final readonly class OcrFieldDefinition
{
    /**
     * @param array<string, string>       $options
     * @param list<array<string, mixed>> $columns
     */
    public function __construct(
        public string $path,
        public string $label,
        public string $type,
        public bool $collection = false,
        public array $options = [],
        public array $columns = [],
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['path' => $this->path, 'label' => $this->label, 'type' => $this->type, 'collection' => $this->collection, 'options' => $this->options, 'columns' => $this->columns];
    }
}
