<?php

declare(strict_types=1);

namespace App\Tests\Shared\Monolog;

use App\Shared\Monolog\DoctrineDbalLogHandler;
use Doctrine\DBAL\Connection;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class DoctrineDbalLogHandlerTest extends TestCase
{
    public function testUnWarningEstPersisteQuelQueSoitLeChannel(): void
    {
        $captures = [];
        $handler = new DoctrineDbalLogHandler($this->connexion($captures));
        $handler->handle($this->record(Level::Warning, 'app', 'requête lente', ['duration' => 2.1]));

        self::assertCount(1, $captures);
        self::assertSame('app', $captures[0]['channel']);
        self::assertSame(Level::Warning->value, $captures[0]['level']);
        self::assertSame('requête lente', $captures[0]['message']);
        self::assertStringContainsString('2.1', (string) $captures[0]['context']);
    }

    public function testLInfoDesChannelsMetierEstPersistee(): void
    {
        $captures = [];
        $handler = new DoctrineDbalLogHandler($this->connexion($captures));
        $handler->handle($this->record(Level::Info, 'marketplace_sync', 'fiche synchronisée'));

        self::assertCount(1, $captures);
    }

    public function testLInfoDesAutresChannelsEstIgnoree(): void
    {
        $captures = [];
        $handler = new DoctrineDbalLogHandler($this->connexion($captures));
        $handler->handle($this->record(Level::Info, 'app', 'bruit applicatif'));
        $handler->handle($this->record(Level::Debug, 'marketplace_sync', 'détail fin'));

        self::assertCount(0, $captures);
    }

    public function testUnePanneBddEstAvaleeSansBoucler(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('insert')->willThrowException(new \RuntimeException('MySQL down'));
        $handler = new DoctrineDbalLogHandler($connection);

        $handler->handle($this->record(Level::Error, 'app', 'erreur pendant la panne'));

        // Arriver ici sans exception est précisément le comportement attendu.
        $this->addToAssertionCount(1);
    }

    public function testLesExceptionsDuContexteSontNormalisees(): void
    {
        $captures = [];
        $handler = new DoctrineDbalLogHandler($this->connexion($captures));
        $handler->handle($this->record(Level::Error, 'messenger', 'worker.message.failed_definitively', [
            'exception' => new \RuntimeException('boom'),
        ]));

        self::assertCount(1, $captures);
        self::assertStringContainsString('boom', (string) $captures[0]['context']);
    }

    /**
     * @param array<mixed> $context
     */
    private function record(Level $level, string $channel, string $message, array $context = []): LogRecord
    {
        return new LogRecord(new \Monolog\JsonSerializableDateTimeImmutable(true), $channel, $level, $message, $context);
    }

    /**
     * @param list<array<string, mixed>> $captures
     */
    private function connexion(array &$captures): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('insert')->willReturnCallback(
            static function (string $table, array $data) use (&$captures): int {
                $captures[] = $data;

                return 1;
            },
        );

        return $connection;
    }
}
