<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class RestoreControllerTest extends WebTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (
            !str_starts_with(
                (string) getenv('TEST_MESSENGER_PIM_DSN'),
                'doctrine://',
            )
        ) {
            self::markTestSkipped('Database integration is disabled.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testHistoryPageShowsRestoreButtonAndRestoresField(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clear();

        $user = new User('validator-restore@example.test', [
            'ROLE_BP_VALIDATOR',
        ]);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Avant');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $lieu->changeLabel('Après');
        $entityManager->flush();

        $client->loginUser($user);
        $crawler = $client->request(
            'GET',
            '/referentiel/lieux/fiche/'.$lieu->id().'/historique',
        );
        self::assertResponseIsSuccessful();
        $forms = $crawler->filter(
            'form[action*="/referentiel/historique/changes/"]',
        );
        self::assertGreaterThan(
            0,
            $forms->count(),
            'Le bouton « Restaurer » doit apparaître sur la page historique.',
        );

        $client->submit($forms->first()->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'restauré');
        $entityManager->clear();
        $label = $this->connection->fetchOne(
            'SELECT label FROM pim_fiche WHERE id = ?',
            [$lieu->fiche()->id()->toBinary()],
        );
        self::assertSame('Avant', $label);
    }

    private function clear(): void
    {
        foreach (
            [
                'audit_change',
                'audit_revision',
                'outbox_message',
                'account_user',
                'pim_lieu_administratif',
                'pim_lieu_tarification',
                'pim_lieu',
                'pim_fiche',
                'pim_localisation',
            ] as $table
        ) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
