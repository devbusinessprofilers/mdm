<?php

namespace App\Pim\Model\ProviderPortal\Pagination;

class PaginationScope
{
    public const VIEW = 7;
    public const BREAK = 5;

    /** @var PaginationItem[] */
    public array $items = [];
    public bool $disableLeftArrow = false;
    public bool $disableRightArrow = false;

    public function __construct(
        public int $total,
        public int $current,
    ) {
        $this->initArrows();
        $this->initItems();
    }

    public function initArrows(): void
    {
        if ($this->total <= self::VIEW) {
            $this->disableLeftArrow = true;
            $this->disableRightArrow = true;

            return;
        }

        if (1 === $this->current) {
            $this->disableLeftArrow = true;
        } elseif ($this->current === $this->total) {
            $this->disableRightArrow = true;
        }
    }

    public function initItems(): void
    {
        if ($this->total <= self::VIEW) {
            for ($i = 1; $i <= $this->total; ++$i) {
                $this->items[] = $this->addMiddleItem($i);
            }

            return;
        }

        for ($i = 1; $i <= $this->total; ++$i) {
            $maybeItem = match ($i) {
                1 => $this->addFirstItem(),
                $this->total => $this->addLastItem(),
                default => $this->handleMiddleItem($i),
            };

            if (null !== $maybeItem) {
                $this->items[] = $maybeItem;
            }
        }
    }

    private function handleMiddleItem(int $page): ?PaginationItem
    {
        if ($page === $this->current) {
            return $this->addMiddleItem($page);
        }

        if ($page === $this->current + 1 && $this->current < $this->total) {
            return $this->addMiddleItem($page);
        }

        if ($page === $this->current - 1 && $this->current > 1) {
            return $this->addMiddleItem($page);
        }

        if ($this->current < self::BREAK && $page <= self::BREAK) {
            return $this->addMiddleItem($page);
        }

        if ($this->current > $this->total - self::BREAK + 1 && $page >= $this->total - self::BREAK + 1) {
            return $this->addMiddleItem($page);
        }

        if (2 === $page || $page === $this->total - 1) {
            return $this->addSpacerItem();
        }

        return null;
    }

    private function addFirstItem(): PaginationItem
    {
        return (new PaginationItem('1', 1))
            ->setIsCurrent(1 === $this->current)
        ;
    }

    private function addMiddleItem(int $page): PaginationItem
    {
        return (new PaginationItem((string) $page, $page))
            ->setIsCurrent($this->current === $page)
        ;
    }

    private function addSpacerItem(): PaginationItem
    {
        return (new PaginationItem('...'))
            ->setIsSpacer(true)
        ;
    }

    private function addLastItem(): PaginationItem
    {
        return (new PaginationItem((string) $this->total, $this->total))
            ->setIsCurrent($this->current === $this->total)
        ;
    }
}
