<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Endpoints ux-autocomplete de la liaison Lieu ↔ Restaurant : la recherche
 * passe par les champs imbriqués fiche.label/fiche.code (EntitySearchUtil
 * joint sa propre association « fiche » — garde anti-collision d'alias) et
 * les fiches archivées sont exclues.
 */
#[Group('database')]
final class LiaisonAutocompleteIntegrationTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->entityManager->clear();
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testLesDeuxAutocompletesCherchentParLibelleEtExcluentLesArchivees(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clearTables();

        $editor = new User('editor@example.com', ['ROLE_BP_EDITOR']);
        $editor->setPassword('test-password-hash');
        $lieu = new Lieu();
        $lieu->changeLabel('Château autocomplete');
        $lieuArchive = new Lieu();
        $lieuArchive->changeLabel('Château archivé');
        $lieuArchive->fiche()->archive('tester');
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot autocomplete');
        $this->entityManager->persist($editor);
        $this->entityManager->persist($lieu);
        $this->entityManager->persist($lieuArchive);
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
        $client->loginUser($editor);

        $client->request('GET', '/autocomplete/lieu_autocomplete_type?query=Château');
        self::assertResponseIsSuccessful();
        /** @var array{results: list<array{value: string, text: string}>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([[
            'value' => $lieu->id(),
            'text' => sprintf('Château autocomplete — LIE-%06d', $lieu->code()),
        ]], $payload['results'], 'Le lieu archivé ne doit pas être proposé.');

        $client->request('GET', '/autocomplete/restaurant_autocomplete_type?query=Bistrot');
        self::assertResponseIsSuccessful();
        /** @var array{results: list<array{value: string, text: string}>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([[
            'value' => $restaurant->id(),
            'text' => sprintf('Bistrot autocomplete — RES-%06d', $restaurant->code()),
        ]], $payload['results']);
    }

    private function clearTables(): void
    {
        foreach (
            [
                'outbox_message',
                'pim_fiche_administratif',
                'pim_lieu_tarification',
                'pim_restaurant',
                'pim_lieu',
                'pim_fiche',
                'pim_localisation',
                'account_user',
            ] as $table
        ) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
