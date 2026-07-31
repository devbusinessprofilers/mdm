<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Dam\Repository\MediaAssetRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class MediaAssetRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (
            !str_starts_with(
                (string) getenv('TEST_MESSENGER_PIM_DSN'),
                'doctrine://',
            )
        ) {
            self::markTestSkipped(
                'Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.',
            );
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testFindByStringIdsFetchJoinsRenditions(): void
    {
        $first = $this->createAsset('lieux/a/original.jpg');
        $second = $this->createAsset('lieux/b/original.jpg');
        $this->entityManager->flush();
        $firstId = $first->id();
        $secondId = $second->id();
        $this->entityManager->clear();
        /** @var MediaAssetRepository $repository */
        $repository = $this->entityManager->getRepository(MediaAsset::class);
        $debugDataHolder = self::getContainer()->get(
            'doctrine.debug_data_holder',
        );
        $debugDataHolder->reset();
        $assets = $repository->findByStringIds([
            $firstId,
            $secondId,
            'not-a-ulid',
        ]);
        self::assertCount(2, $assets);
        foreach ($assets as $asset) {
            $renditions = $asset->renditions();
            self::assertInstanceOf(PersistentCollection::class, $renditions);
            self::assertTrue($renditions->isInitialized());
            self::assertNotNull($asset->rendition('large'));
        }
        $queries = array_merge(...array_values($debugDataHolder->getData()));
        $selects = array_values(
            array_filter(
                $queries,
                static fn (array $query): bool => str_starts_with(
                    ltrim((string) $query['sql']),
                    'SELECT',
                ),
            ),
        );
        self::assertCount(
            1,
            $selects,
            implode(
                "\n",
                array_map(
                    static fn (array $query): string => (string) $query['sql'],
                    $selects,
                ),
            ),
        );
    }

    private function createAsset(string $storageKey): MediaAsset
    {
        $asset = new MediaAsset(
            new Ulid(),
            $storageKey,
            'original.jpg',
            'image/jpeg',
            1024,
            sha1($storageKey),
        );
        $asset->addRendition(
            new MediaRendition(
                $asset,
                'large',
                $storageKey.'/renditions/large.webp',
                960,
                480,
                512,
            ),
        );
        $asset->addRendition(
            new MediaRendition(
                $asset,
                'small',
                $storageKey.'/renditions/small.webp',
                200,
                100,
                128,
            ),
        );
        $this->entityManager->persist($asset);

        return $asset;
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM dam_media_rendition');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
    }
}
