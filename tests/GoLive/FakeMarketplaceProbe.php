<?php

declare(strict_types=1);

namespace App\Tests\GoLive;

use App\GoLive\EtapeEtat;
use App\GoLive\MarketplaceProbeInterface;

final readonly class FakeMarketplaceProbe implements MarketplaceProbeInterface
{
    public function __construct(private EtapeEtat $etat)
    {
    }

    public function verifier(): EtapeEtat
    {
        return $this->etat;
    }
}
