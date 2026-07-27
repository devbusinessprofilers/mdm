<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Outbox\OutboxRelay;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:consume', description: 'Publie en continu les événements de l’outbox vers Messenger.')]
final class OutboxConsumeCommand extends Command implements SignalableCommandInterface
{
    private bool $shouldStop = false;

    public function __construct(private readonly OutboxRelay $relay)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d’événements par lot.', '100')
            ->addOption('lease', null, InputOption::VALUE_REQUIRED, 'Durée du verrou en secondes.', '300')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Pause en secondes lorsque l’outbox est vide.', '1')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Durée maximale du processus en secondes.', '3600')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Mémoire maximale, avec suffixe K, M ou G.', '128M');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $lease = max(1, (int) $input->getOption('lease'));
        $sleep = max(0, (int) $input->getOption('sleep'));
        $timeLimit = max(1, (int) $input->getOption('time-limit'));
        $memoryLimit = $this->convertToBytes((string) $input->getOption('memory-limit'));
        $deadline = time() + $timeLimit;
        $host = gethostname();
        $workerId = substr(sprintf('%s-%d', false === $host ? 'outbox' : $host, getmypid()), 0, 64);

        $io->comment(sprintf('Outbox worker %s started.', $workerId));

        while (!$this->shouldStop && time() < $deadline && memory_get_usage(true) < $memoryLimit) {
            $result = $this->relay->relayBatch($workerId, $batchSize, $lease);

            if (0 === $result->claimed && $sleep > 0) {
                sleep($sleep);
            }
        }

        $io->comment('Outbox worker stopped cleanly.');

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return extension_loaded('pcntl') ? [SIGTERM, SIGINT, SIGQUIT] : [];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower(trim($memoryLimit));
        $value = (float) rtrim($memoryLimit, 'kmgb');

        return match (substr(rtrim($memoryLimit, 'b'), -1)) {
            'g' => (int) ($value * 1024 * 1024 * 1024),
            'm' => (int) ($value * 1024 * 1024),
            'k' => (int) ($value * 1024),
            default => (int) $value,
        };
    }
}
