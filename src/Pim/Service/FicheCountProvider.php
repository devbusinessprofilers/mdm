<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class FicheCountProvider
{
    private const TTL_SECONDS = 60;

    public function __construct(
        private FicheRepository $fiches,
        private CacheInterface $cache,
    ) {
    }

    public function totalByType(TypeFiche $type): int
    {
        return $this->cache->get(
            sprintf('pim_fiche_count_total_%s', $type->value),
            function (ItemInterface $item) use ($type): int {
                $item->expiresAfter(self::TTL_SECONDS);

                return $this->fiches->countByType($type);
            },
        );
    }

    public function countByStatus(TypeFiche $type, StatutFiche $status): int
    {
        return $this->cache->get(
            sprintf(
                'pim_fiche_count_%s_%s',
                $type->value,
                $status->value,
            ),
            function (ItemInterface $item) use ($type, $status): int {
                $item->expiresAfter(self::TTL_SECONDS);

                return $this->fiches->countByTypeAndStatus($type, $status);
            },
        );
    }
}
