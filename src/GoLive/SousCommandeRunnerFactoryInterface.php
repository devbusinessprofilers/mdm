<?php

declare(strict_types=1);

namespace App\GoLive;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

interface SousCommandeRunnerFactoryInterface
{
    public function creer(Application $application, OutputInterface $output): SousCommandeRunnerInterface;
}
