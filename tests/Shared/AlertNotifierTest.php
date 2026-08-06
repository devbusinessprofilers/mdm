<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Alert\AlertNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class AlertNotifierTest extends TestCase
{
    public function testDuplicateAlertsAreSentOnlyOncePerWindow(): void
    {
        $sent = [];
        $notifier = $this->notifier('ops@example.test', $sent);

        $notifier->notify('worker_failure', 'App\Foo:RuntimeException', 'Sujet', 'Corps');
        $notifier->notify('worker_failure', 'App\Foo:RuntimeException', 'Sujet', 'Corps');
        $notifier->notify('worker_failure', 'App\Bar:RuntimeException', 'Autre sujet', 'Corps');

        self::assertCount(2, $sent);
        $first = $sent[0];
        self::assertInstanceOf(Email::class, $first);
        self::assertSame('[MDM][ALERTE] Sujet', $first->getSubject());
        self::assertSame('ops@example.test', $first->getTo()[0]->getAddress());
        self::assertSame('noreply@example.test', $first->getFrom()[0]->getAddress());
    }

    public function testNothingIsSentWhenRecipientIsNotConfigured(): void
    {
        $sent = [];
        $this->notifier('', $sent)->notify('worker_failure', 'x', 'Sujet', 'Corps');
        self::assertSame([], $sent);
    }

    /** @param list<RawMessage> $sent */
    private function notifier(string $recipient, array &$sent): AlertNotifier
    {
        $mailer = new class($sent) implements MailerInterface {
            /** @param list<RawMessage> $sent */
            public function __construct(private array &$sent) {}

            public function send(RawMessage $message, mixed $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };

        return new AlertNotifier($mailer, new ArrayAdapter(), new NullLogger(), $recipient, 'noreply@example.test');
    }
}
