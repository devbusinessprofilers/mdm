<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Repository\LieuRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class LieuRepositoryTest extends KernelTestCase
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

    public function testFindOneByFicheWithLocalisationHydratesWithoutLazyLoads(): void
    {
        $lieu = new Lieu();
        $lieu->changeCode(42);
        $lieu->changeLabel('Palais des congrès');
        $localisation = new Localisation();
        $localisation->changeCodePostal('06000');
        $lieu->changeLocalisation($localisation);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $ficheId = $lieu->fiche()->id();
        $this->entityManager->clear();

        $fiche = $this->entityManager->find(Fiche::class, $ficheId);
        self::assertInstanceOf(Fiche::class, $fiche);

        /** @var LieuRepository $repository */
        $repository = $this->entityManager->getRepository(Lieu::class);
        $debugDataHolder = self::getContainer()->get('doctrine.debug_data_holder');
        $debugDataHolder->reset();

        $reloaded = $repository->findOneByFicheWithLocalisation($fiche);
        self::assertInstanceOf(Lieu::class, $reloaded);
        self::assertSame($lieu->id(), $reloaded->id());
        // Reading the address through the fiche must not trigger a lazy load.
        self::assertSame('06000', $fiche->localisation()?->codePostal());

        $queries = array_merge(...array_values($debugDataHolder->getData()));
        $selects = array_values(array_filter(
            $queries,
            static fn (array $query): bool => str_starts_with(ltrim((string) $query['sql']), 'SELECT'),
        ));
        self::assertCount(1, $selects, implode("\n", array_map(
            static fn (array $query): string => (string) $query['sql'],
            $selects,
        )));
    }

    public function testRessourceLieuMapsTheDamAssetIndex(): void
    {
        $indexes = $this->entityManager->getClassMetadata(RessourceLieu::class)->table['indexes'] ?? [];

        self::assertArrayHasKey('IDX_PIM_RESSOURCE_DAM_ASSET', $indexes);
        self::assertSame(['dam_asset_id'], $indexes['IDX_PIM_RESSOURCE_DAM_ASSET']['columns']);
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }
}
