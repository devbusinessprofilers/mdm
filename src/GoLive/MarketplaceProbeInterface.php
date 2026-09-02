<?php

declare(strict_types=1);

namespace App\GoLive;

interface MarketplaceProbeInterface
{
    /** Teste l'authentification réelle auprès de la marketplace, sans autre effet. */
    public function verifier(): EtapeEtat;
}
