<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Alert\AlertNotifier;
use App\Shared\Alert\Message\CheckFailedQueue;
use App\Shared\Alert\MessageHandler\CheckFailedQueueHandler;
use App\Tests\Support\ParametresFixes;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[Group('database')]
final class CheckFailedQueueHandlerTest extends KernelTestCase
{
    public function testAlertIsSentWhenFailedQueueReachesThreshold(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        $sent = [];
        $mailer = new class($sent) implements MailerInterface {
            /** @param list<RawMessage> $sent */
            public function __construct(private array &$sent) {}

            public function send(RawMessage $message, mixed $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
        $notifier = new AlertNotifier($mailer, new ArrayAdapter(), new NullLogger(), new ParametresFixes(['alerte.email' => 'ops@example.test']), 'noreply@example.test');

        // Sous le seuil : rien.
        (new CheckFailedQueueHandler($connection, $notifier, new ParametresFixes(['alerte.seuil_file_echec' => (string) PHP_INT_MAX])))(new CheckFailedQueue());
        self::assertCount(0, $sent);

        // Seuil à 0 : toujours atteint, une alerte part.
        (new CheckFailedQueueHandler($connection, $notifier, new ParametresFixes(['alerte.seuil_file_echec' => '0'])))(new CheckFailedQueue());
        self::assertCount(1, $sent);
        $email = $sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('Messages en échec', (string) $email->getSubject());
        self::assertStringContainsString('outbox', (string) $email->getSubject());
    }
}
