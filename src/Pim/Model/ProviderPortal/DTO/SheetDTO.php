<?php

namespace App\Pim\Model\ProviderPortal\DTO;

use App\Pim\Enum\ProviderPortal\SheetTypeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Tag\TagVariantEnum;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SheetDTO
{
    public ?string $uniqueId = null;

    public ?string $slug = null;

    public ?string $name = null;

    public ?SheetTypeEnum $type = null;

    public bool $isOnline = false;

    public int $progress = 0;

    /**
     * @var array<array{label: string, variant: TagVariantEnum}>
     */
    public array $tags = [];

    public static function mock(?SheetTypeEnum $type = null, ?string $name = null, ?string $uniqueId = null): self
    {
        $data = new self();

        $data->type = $type ?? SheetTypeEnum::PLACE;
        $data->name = $name ?? 'Fiche '.$data->type->value.' mock';
        $data->uniqueId = $uniqueId ?? uniqid(sprintf('sheet_%s_', $data->type->value));

        $data->slug = (new AsciiSlugger())->slug($data->name.'-'.$data->uniqueId)->lower()->toString();

        // HACK: use name length to resolve properties and keep same values when testing...
        $length = strlen($name) % 11;
        $data->progress = 10 * $length;
        if ($data->progress >= 60) {
            $data->isOnline = true;
        }

        $data->tags[] = (0 === $length % 2)
            ? ['label' => 'page.sheet.tag.premium', 'variant' => TagVariantEnum::OLD_GOLD]
            : ['label' => 'page.sheet.tag.standard', 'variant' => TagVariantEnum::PRIMARY_3]
        ;

        return $data;
    }
}
