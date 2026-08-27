<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Level;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class PerformanceControllerTest extends WebTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DELETE FROM log_entry');
            $this->connection->executeStatement('DELETE FROM worker_heartbeat');
            $this->connection->executeStatement('DELETE FROM perf_sample');
            $this->connection->executeStatement('DELETE FROM account_user');
        }
        parent::tearDown();
    }

    public function testLaPageEstReserveeAuxAdmins(): void
    {
        $client = self::createClient();

        $client->loginUser($this->persistUser('editor-performance@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/performance');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($this->persistUser('admin-performance@example.test', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/performance');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Performance');
        // Les six workers attendus ont chacun leur carte, même sans heartbeat.
        foreach (['worker-pim', 'worker-dam', 'worker-batch', 'worker-mail', 'worker-outbox', 'cron-scheduler'] as $worker) {
            self::assertSelectorExists(sprintf('[data-perf-worker="%s"]', $worker));
        }
    }

    public function testLeFragmentTableauxPorteLIdAttenduParLePoll(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('admin-performance@example.test', ['ROLE_ADMIN']));

        // poll_controller retrouve #perf-tableaux dans la réponse pour ne
        // remplacer que son contenu : sans cette racine, il se rabat sur le
        // premier élément et le tableau DLQ disparaît au premier
        // rafraîchissement.
        $client->request('GET', '/admin/performance/tableaux');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#perf-tableaux');
        self::assertSelectorTextContains('#perf-tableaux', 'Messages en échec définitif');
    }

    public function testLEndpointDataRenvoieLInstantaneJson(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('admin-performance@example.test', ['ROLE_ADMIN']));
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO worker_heartbeat (
                worker_key, worker_name, hostname, pid, transports, status,
                started_at, last_seen_at, memory_bytes, memory_peak_bytes,
                busy_ms_total, handled_total, failed_total, retried_total,
                message_stats, transport_stats
            ) VALUES (
                'test:1', 'worker-pim', 'test', 1, '["pim"]', 'running',
                DATE_SUB(NOW(), INTERVAL 300 SECOND), NOW(), 104857600, 104857600,
                30000, 12, 0, 0, '{}', '{}'
            )
            SQL);

        $client->request('GET', '/admin/performance/data?fenetre=60');
        self::assertResponseIsSuccessful();
        $donnees = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($donnees);
        self::assertSame(60, $donnees['fenetreMinutes']);
        self::assertArrayHasKey('series', $donnees);
        self::assertArrayHasKey('labels', $donnees['series']);

        $pim = null;
        foreach ($donnees['workers'] as $worker) {
            if ('worker-pim' === $worker['name']) {
                $pim = $worker;
            }
        }
        self::assertIsArray($pim);
        self::assertSame('actif', $pim['etat']);
        // Pas encore d'échantillons : ratio depuis le démarrage (30 s occupées sur 300).
        self::assertEqualsWithDelta(10.0, $pim['chargePct'], 1.0);
    }

    public function testLaVisionneuseFiltreLesLogs(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('admin-performance@example.test', ['ROLE_ADMIN']));
        $this->connection->insert('log_entry', [
            'logged_at' => (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s'),
            'channel' => 'marketplace_sync',
            'level' => Level::Warning->value,
            'message' => 'timeout marketplace pendant la synchro',
            'context' => '{"fiche": "01ABC"}',
        ]);
        $this->connection->insert('log_entry', [
            'logged_at' => (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s'),
            'channel' => 'mail',
            'level' => Level::Info->value,
            'message' => 'relance envoyée',
        ]);

        $client->request('GET', '/admin/performance', ['canal' => 'marketplace_sync', 'niveau' => Level::Warning->value]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'timeout marketplace pendant la synchro');
        self::assertSelectorTextNotContains('body', 'relance envoyée');
    }

    public function testLesActionsFailedExigentLeJetonCsrf(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('admin-performance@example.test', ['ROLE_ADMIN']));

        $client->request('POST', '/admin/performance/failed/12345/reessayer');
        self::assertResponseStatusCodeSame(403);
    }

    /** @param list<string> $roles */
    private function persistUser(string $email, array $roles = []): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM account_user');

        $user = new User($email, $roles);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
