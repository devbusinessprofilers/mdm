<?php

namespace App\Pim\Twig\Components\Visibility;

use App\Pim\Enum\ProviderPortal\VisibilityPackEnum;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;
use App\Pim\Model\ProviderPortal\Visibility\VisibilityPack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Packs
{
    public SheetDTO $sheet;

    /**
     * @var array<VisibilityPack>
     */
    public array $packs = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getPacks(): array
    {
        return $this->mock();
    }

    private function mock(): array
    {
        // NOTE: depends on current sheet!
        return [
            (new VisibilityPack(VisibilityPackEnum::FREE))
                ->setIsSelected(true)
                ->setAction('#pack')
                ->addOption($this->translator->trans('visibility.pack.option.free'))
                ->addOption($this->translator->trans('visibility.pack.option.seo')),
            (new VisibilityPack(VisibilityPackEnum::ESSENTIAL))
                ->setIsSelected(true)
                ->setAction('#pack')
                ->addOption($this->translator->trans('visibility.pack.option.seo'))
                ->addOption($this->translator->trans('visibility.pack.option.competitive_analysis'))
                ->addOption($this->translator->trans('visibility.pack.option.top_digital_guide'))
                ->addOption($this->translator->trans('visibility.pack.option.one_month_highlight')),
            (new VisibilityPack(VisibilityPackEnum::PERFORMANCE))
                ->setAction('#pack')
                ->addOption($this->translator->trans('visibility.pack.option.seo'))
                ->addOption($this->translator->trans('visibility.pack.option.competitive_analysis'))
                ->addOption($this->translator->trans('visibility.pack.option.top_digital_guide'))
                ->addOption($this->translator->trans('visibility.pack.option.one_month_highlight'))
                ->addOption($this->translator->trans('visibility.pack.option.one_newsletter_group_communication')),
            (new VisibilityPack(VisibilityPackEnum::TARGETED_AUDIENCE))
                ->setAction('#pack')
                ->addOption($this->translator->trans('visibility.pack.option.seo'))
                ->addOption($this->translator->trans('visibility.pack.option.competitive_analysis'))
                ->addOption($this->translator->trans('visibility.pack.option.top_digital_guide'))
                ->addOption($this->translator->trans('visibility.pack.option.one_month_highlight'))
                ->addOption($this->translator->trans('visibility.pack.option.one_newsletter_group_communication'))
                ->addOption($this->translator->trans('visibility.pack.option.top_10_inclusion')),
        ];
    }
}
