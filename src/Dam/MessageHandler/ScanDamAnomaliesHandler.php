<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Message\ScanDamAnomalies;
use App\Dam\Service\DamAnomalyScanner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ScanDamAnomaliesHandler
{
    public function __construct(private DamAnomalyScanner $scanner)
    {
    }

    public function __invoke(ScanDamAnomalies $message): void
    {
        $this->scanner->scan();
    }
}
