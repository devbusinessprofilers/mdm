<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\StatutFiche;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\LieuRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class LieuPersistenceTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testLieuAndLocalisationArePersistedAndRemovedTogether(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Palais des congrès');
        $firstLocalisation = new Localisation();
        $firstLocalisation->changeCodePostal('06000');
        $lieu->changeLocalisation($firstLocalisation);

        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        self::assertSame($lieu->id(), $this->entityManager->getRepository(Lieu::class)->find($lieu->id())?->id());
        self::assertSame(1, $this->countRows('pim_lieu'));
        self::assertSame(1, $this->countRows('pim_localisation'));

        $secondLocalisation = new Localisation();
        $secondLocalisation->changeCodePostal('75001');
        $lieu->changeLocalisation($secondLocalisation);
        $this->entityManager->flush();

        self::assertSame(1, $this->countRows('pim_localisation'));
        self::assertSame('75001', $this->entityManager->getRepository(Localisation::class)->find($secondLocalisation->id())?->codePostal());

        $this->entityManager->remove($lieu);
        $this->entityManager->flush();

        self::assertSame(0, $this->countRows('pim_lieu'));
        self::assertSame(0, $this->countRows('pim_localisation'));
    }

    public function testKeysetPaginationDoesNotDuplicateOrOmitEqualTimestamps(): void
    {
        /** @var array<string, StatutFiche> $expected */
        $expected = [];
        for ($index = 1; $index <= 7; ++$index) {
            $lieu = new Lieu();
            $lieu->changeLabel('Lieu '.$index);
            if (0 === $index % 2) {
                $lieu->fiche()->publishForImport();
            }
            $expected[$lieu->id()] = $lieu->fiche()->status();
            $this->entityManager->persist($lieu);
        }
        $this->entityManager->flush();
        $this->connection->executeStatement("UPDATE pim_fiche SET updated_at = '2026-07-29 12:00:00'");
        $this->entityManager->clear();

        /** @var LieuRepository $repository */
        $repository = $this->entityManager->getRepository(Lieu::class);
        $allIds = $this->collectPaginatedIds($repository);
        self::assertCount(count($expected), $allIds);
        self::assertCount(count($allIds), array_unique($allIds));
        self::assertEqualsCanonicalizing(array_keys($expected), $allIds);

        $publishedIds = $this->collectPaginatedIds($repository, StatutFiche::Publiee);
        $expectedPublishedIds = array_keys(array_filter(
            $expected,
            static fn (StatutFiche $status): bool => StatutFiche::Publiee === $status,
        ));
        self::assertCount(count($expectedPublishedIds), $publishedIds);
        self::assertCount(count($publishedIds), array_unique($publishedIds));
        self::assertEqualsCanonicalizing($expectedPublishedIds, $publishedIds);
    }

    public function testTwoEavReplacementsInitializeTheCollectionOnlyOnce(): void
    {
        $lieu = new Lieu();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $ficheId = $lieu->fiche()->id();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $reloaded);

        $debugDataHolder = self::getContainer()->get('doctrine.debug_data_holder');
        $debugDataHolder->reset();
        $typologyId = LieuLovCatalog::valueId('GENERALE_TYPOLOGIE', 'GENERALE_TYPOLOGIE_1');
        $serviceId = LieuLovCatalog::valueId('SERVICES', 'SERVICES_1');
        $reloaded->replaceAttributeValues('GENERALE_TYPOLOGIE', [$typologyId]);
        $reloaded->replaceAttributeValues('SERVICES', [$serviceId]);
        $this->entityManager->flush();

        $queries = array_merge(...array_values($debugDataHolder->getData()));
        $queries = array_values(array_filter(
            $queries,
            static fn (array $query): bool => !in_array($query['sql'], ['"START TRANSACTION"', '"COMMIT"'], true),
        ));
        $attributeSelects = array_filter(
            $queries,
            static fn (array $query): bool => str_starts_with(ltrim((string) $query['sql']), 'SELECT')
                && str_contains((string) $query['sql'], 'pim_fiche_attribute_value'),
        );

        self::assertCount(1, $attributeSelects);
        self::assertLessThanOrEqual(6, count($queries), implode("\n", array_map(
            static fn (array $query): string => (string) $query['sql'],
            $queries,
        )));
        self::assertSame([$typologyId], $reloaded->valueIdsFor('GENERALE_TYPOLOGIE'));
        self::assertSame([$serviceId], $reloaded->valueIdsFor('SERVICES'));
    }

    /** @return list<string> */
    private function collectPaginatedIds(LieuRepository $repository, ?StatutFiche $status = null): array
    {
        $ids = [];
        $cursor = null;

        do {
            $page = $repository->findListPage($cursor, 2, $status);
            foreach ($page->items as $item) {
                $ids[] = $item->id;
            }
            $cursor = FicheCursor::decode($page->nextCursor);
        } while (null !== $cursor);

        return $ids;
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_acces_lieu');
        $this->connection->executeStatement('DELETE FROM pim_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
    }
}
