<?php

declare(strict_types=1);

namespace App\Dashboard\Scheduler;

use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\Message\ComputeFieldFillRates;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('dashboard')]
final class DashboardScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->add(RecurringMessage::every('15 minutes', new ComputeDashboardStats()))
            // Parcourt toutes les fiches : quotidien, et redispatché vers le
            // worker pim pour ne pas occuper le consumer cron du scheduler.
            ->add(RecurringMessage::cron('30 4 * * *', new RedispatchMessage(new ComputeFieldFillRates(), 'pim'), new \DateTimeZone('Europe/Paris')));
    }
}
