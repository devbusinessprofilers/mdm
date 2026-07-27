<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

interface OutboxPublisherInterface
{
    /**
     * Enregistre l'événement dans l'Unit of Work courante sans déclencher de flush.
     */
    public function enqueue(object $message): string;
}
