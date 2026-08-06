<?php

declare(strict_types=1);

namespace App\Dashboard\Scheduler;

use App\Dashboard\Message\ComputeDashboardStats;
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
            ->add(RecurringMessage::every('15 minutes', new ComputeDashboardStats()));
    }
}
