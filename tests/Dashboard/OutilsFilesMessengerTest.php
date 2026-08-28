<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Dashboard\Repository\JournalTraitementsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les cartes de synthèse de /outils comptent toutes les files Messenger :
 * « En cours ou en file » = tout sauf la DLQ, « Avec erreurs » = la file failed.
 */
#[Group('database')]
final class OutilsFilesMessengerTest extends WebTestCase
{
    public function testEtatFilesMessengerVentileParEtatReel(): void
    {
        $client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM messenger_messages');
        // 4 en file (prêts), 2 en cours (livrés), 3 planifiés (dispo plus tard), 2 en erreur.
        $this->seed($connection, 'pim', 4, delivered: false, futur: false);
        $this->seed($connection, 'pim', 2, delivered: true, futur: false);
        $this->seed($connection, 'dam', 3, delivered: false, futur: true);
        $this->seed($connection, 'failed', 2, delivered: false, futur: false);

        $etat = self::getContainer()->get(JournalTraitementsRepository::class)->etatFilesMessenger();
        self::assertSame(['en_file' => 4, 'en_cours' => 2, 'planifies' => 3, 'echecs' => 2], $etat);

        $this->loginAdmin($client);
        $client->request('GET', '/outils');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#outils-indicateurs');
        self::assertSelectorTextContains('turbo-frame#outils-indicateurs', 'En file');

        // Le fragment rechargé par le polling doit répondre seul.
        $client->request('GET', '/outils/indicateurs');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#outils-indicateurs[data-controller="poll"]');
        $contenu = (string) $client->getResponse()->getContent();
        foreach (['En file', 'En cours', 'Planifiés', 'En erreur', 'Outbox'] as $libelle) {
            self::assertStringContainsString($libelle, $contenu);
        }
        // L'indicateur « En erreur » renvoie au journal filtré, pas à l'admin.
        self::assertStringContainsString('/outils?erreurs=1', $contenu);
        // Files non vides : les icônes tournent pour signaler l'activité.
        self::assertStringContainsString('animate-spin', $contenu);
    }

    public function testEcranResisteAUneFileVide(): void
    {
        $client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM messenger_messages');

        self::assertSame(
            ['en_file' => 0, 'en_cours' => 0, 'planifies' => 0, 'echecs' => 0],
            self::getContainer()->get(JournalTraitementsRepository::class)->etatFilesMessenger(),
        );

        $this->loginAdmin($client);
        $client->request('GET', '/outils');
        self::assertResponseIsSuccessful();
    }

    private function seed(Connection $connection, string $queue, int $count, bool $delivered = false, bool $futur = false): void
    {
        $available = $futur ? 'UTC_TIMESTAMP() + INTERVAL 1 HOUR' : 'UTC_TIMESTAMP()';
        $deliveredAt = $delivered ? 'UTC_TIMESTAMP()' : 'NULL';
        for ($i = 0; $i < $count; ++$i) {
            $connection->executeStatement(
                sprintf(
                    'INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at)
                     VALUES (:body, :headers, :queue, UTC_TIMESTAMP(), %s, %s)',
                    $available,
                    $deliveredAt,
                ),
                ['body' => '{}', 'headers' => '{}', 'queue' => $queue],
            );
        }
    }

    private function loginAdmin(KernelBrowser $client): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM account_user');
        $user = new User('outils-admin@example.test', ['ROLE_ADMIN']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);
    }
}
