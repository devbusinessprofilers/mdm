<?php

declare(strict_types=1);

namespace App\Tests\GoLive;

use App\GoLive\SousCommandeRunnerFactoryInterface;
use App\GoLive\SousCommandeRunnerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class RecordingSousCommandeRunnerFactory implements SousCommandeRunnerFactoryInterface
{
    public function __construct(private RecordingSousCommandeRunner $runner)
    {
    }

    public function creer(Application $application, OutputInterface $output): SousCommandeRunnerInterface
    {
        return $this->runner;
    }
}
