<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

#[Group('database')]
final class PdoSessionHandlerIntegrationTest extends KernelTestCase
{
    private Connection $connection;
    private PdoSessionHandler $handler;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        // Le service du conteneur parse DATABASE_URL brut et ignorerait le
        // dbname_suffix _test de Doctrine : on construit le handler sur la
        // connexion de test pour valider le schéma migré dans mdm_test.
        $pdo = $this->connection->getNativeConnection();
        self::assertInstanceOf(\PDO::class, $pdo);
        $this->handler = new PdoSessionHandler($pdo);
        $this->connection->executeStatement("DELETE FROM sessions WHERE sess_id LIKE 'test\\_%'");
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement("DELETE FROM sessions WHERE sess_id LIKE 'test\\_%'");
        }

        parent::tearDown();
    }

    public function testHandlerServiceIsWired(): void
    {
        self::assertInstanceOf(PdoSessionHandler::class, self::getContainer()->get(PdoSessionHandler::class));
    }

    public function testFullSessionLifecycleAgainstMigratedSchema(): void
    {
        $sessionId = 'test_'.bin2hex(random_bytes(13));

        self::assertTrue($this->handler->open('', 'PHPSESSID'));
        self::assertSame('', $this->handler->read($sessionId));
        self::assertTrue($this->handler->write($sessionId, 'security|s:7:"payload";'));
        self::assertTrue($this->handler->close());

        self::assertTrue($this->handler->open('', 'PHPSESSID'));
        self::assertSame('security|s:7:"payload";', $this->handler->read($sessionId));
        self::assertTrue($this->handler->close());

        $row = $this->connection->fetchAssociative('SELECT sess_lifetime, sess_time FROM sessions WHERE sess_id = ?', [$sessionId]);
        self::assertNotFalse($row);
        self::assertGreaterThan(0, (int) $row['sess_lifetime']);
        self::assertGreaterThan(0, (int) $row['sess_time']);

        self::assertTrue($this->handler->open('', 'PHPSESSID'));
        $this->handler->read($sessionId);
        self::assertTrue($this->handler->destroy($sessionId));
        self::assertTrue($this->handler->close());
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM sessions WHERE sess_id = ?', [$sessionId]));

        self::assertGreaterThanOrEqual(0, $this->handler->gc(0));
    }
}
