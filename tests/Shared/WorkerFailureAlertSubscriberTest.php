<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Alert\AlertNotifier;
use App\Tests\Support\ParametresFixes;
use App\Shared\Alert\WorkerFailureAlertSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class WorkerFailureAlertSubscriberTest extends TestCase
{
    public function testAlertIsSentOnlyOnFinalFailure(): void
    {
        $sent = [];
        $mailer = new class($sent) implements MailerInterface {
            /** @param list<RawMessage> $sent */
            public function __construct(private array &$sent) {}

            public function send(RawMessage $message, mixed $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
        $subscriber = new WorkerFailureAlertSubscriber(
            new AlertNotifier($mailer, new ArrayAdapter(), new NullLogger(), new ParametresFixes(['alerte.email' => 'ops@example.test']), 'noreply@example.test'),
        );

        $retried = new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'pim', new \RuntimeException('boom'));
        $retried->setForRetry();
        $subscriber($retried);
        self::assertCount(0, $sent);

        $subscriber(new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'pim', new \RuntimeException('boom')));
        self::assertCount(1, $sent);
        $email = $sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('stdClass', (string) $email->getSubject());
        self::assertStringContainsString('RuntimeException', (string) $email->getTextBody());

        // Même échec renvoyé : dédupliqué.
        $subscriber(new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'pim', new \RuntimeException('boom')));
        self::assertCount(1, $sent);
    }
}
