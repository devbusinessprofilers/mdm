<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/** Mailer d'enregistrement : capture les messages envoyés pour les assertions. */
final class RecordingMailer implements MailerInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->sent[] = $message;
    }
}
