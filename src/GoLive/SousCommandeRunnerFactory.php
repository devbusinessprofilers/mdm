<?php

declare(strict_types=1);

namespace App\GoLive;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SousCommandeRunnerFactoryInterface::class)]
final readonly class SousCommandeRunnerFactory implements SousCommandeRunnerFactoryInterface
{
    public function creer(Application $application, OutputInterface $output): SousCommandeRunnerInterface
    {
        return new SousCommandeRunner($application, $output);
    }
}
