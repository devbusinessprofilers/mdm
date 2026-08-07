<?php

namespace App;

use App\Dam\Message\ScanDamAnomalies;
use App\Pim\Message\RemindIncompleteFiches;
use App\Shared\Alert\Message\CheckFailedQueue;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            ->add(RecurringMessage::every('15 minutes', new CheckFailedQueue()))

            // Redispatch : le scan (lourd) s'exécute dans le worker dam, pas
            // dans le consumer cron du scheduler.
            ->add(RecurringMessage::every('1 hour', new RedispatchMessage(new ScanDamAnomalies(), 'dam')))

            // Relances hebdomadaires des fiches incomplètes, exécutées par le
            // worker pim (le fan-out dispatch ensuite un email par fiche).
            ->add(RecurringMessage::cron('0 8 * * 1', new RedispatchMessage(new RemindIncompleteFiches(), 'pim'), new \DateTimeZone('Europe/Paris')))

            // add your own tasks here
            // see https://symfony.com/doc/current/scheduler.html#attaching-recurring-messages-to-a-schedule
        ;
    }
}
