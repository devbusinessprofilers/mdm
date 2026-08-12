<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Alert\AlertNotifier;
use App\Tests\Support\ParametresFixes;
use App\Tests\Support\RecordingMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mime\Email;

final class AlertNotifierTest extends TestCase
{
    public function testDuplicateAlertsAreSentOnlyOncePerWindow(): void
    {
        $mailer = new RecordingMailer();
        $notifier = $this->notifier('ops@example.test', $mailer);

        $notifier->notify('worker_failure', 'App\Foo:RuntimeException', 'Sujet', 'Corps');
        $notifier->notify('worker_failure', 'App\Foo:RuntimeException', 'Sujet', 'Corps');
        $notifier->notify('worker_failure', 'App\Bar:RuntimeException', 'Autre sujet', 'Corps');

        self::assertCount(2, $mailer->sent);
        $first = $mailer->sent[0];
        self::assertInstanceOf(Email::class, $first);
        self::assertSame('[MDM][ALERTE] Sujet', $first->getSubject());
        self::assertSame('ops@example.test', $first->getTo()[0]->getAddress());
        self::assertSame('noreply@example.test', $first->getFrom()[0]->getAddress());
    }

    public function testNothingIsSentWhenRecipientIsNotConfigured(): void
    {
        $mailer = new RecordingMailer();
        $this->notifier('', $mailer)->notify('worker_failure', 'x', 'Sujet', 'Corps');
        self::assertSame([], $mailer->sent);
    }

    private function notifier(string $recipient, RecordingMailer $mailer): AlertNotifier
    {
        return new AlertNotifier($mailer, new ArrayAdapter(), new NullLogger(), new ParametresFixes(['alerte.email' => $recipient]), 'noreply@example.test');
    }
}
