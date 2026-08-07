<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Dam\Enum\DamAnomalyType;
use App\Dam\Repository\DamAnomalyRepository;
use App\Dam\Service\DamAnomalyScanner;
use App\Dam\Service\ImageVariantRegistry;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class DamAnomalyScannerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        parent::tearDown();
    }

    public function testOrphanResourceIsDetectedThenResolved(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Fiche avec ressource orpheline');
        $orphan = $this->photo((string) new Ulid());
        $lieu->addRessource($orphan);

        $healthyAsset = $this->processedAsset(ImageVariantRegistry::names());
        $healthy = $this->photo($healthyAsset->id());
        $lieu->addRessource($healthy);

        $this->entityManager->persist($healthyAsset);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        $scanner = self::getContainer()->get(DamAnomalyScanner::class);
        $scanner->scan();

        $anomalies = self::getContainer()->get(DamAnomalyRepository::class)->allBySubject(DamAnomalyType::OrphanResource);
        self::assertArrayHasKey($orphan->id(), $anomalies);
        self::assertTrue($anomalies[$orphan->id()]->isOpen());
        self::assertArrayNotHasKey($healthy->id(), $anomalies);

        // Corriger la ressource puis rescanner : l'anomalie doit être résolue.
        $orphan->changeDamAssetId($healthyAsset->id());
        $this->entityManager->flush();
        $scanner->scan();

        $anomalies = self::getContainer()->get(DamAnomalyRepository::class)->allBySubject(DamAnomalyType::OrphanResource);
        self::assertArrayHasKey($orphan->id(), $anomalies);
        self::assertFalse($anomalies[$orphan->id()]->isOpen());
    }

    public function testProcessedImageMissingRenditionsIsDetected(): void
    {
        $complete = $this->processedAsset(ImageVariantRegistry::names());
        $incomplete = $this->processedAsset(['large', 'small']);
        $this->entityManager->persist($complete);
        $this->entityManager->persist($incomplete);
        $this->entityManager->flush();

        self::getContainer()->get(DamAnomalyScanner::class)->scan();

        $anomalies = self::getContainer()->get(DamAnomalyRepository::class)->allBySubject(DamAnomalyType::MissingRenditions);
        self::assertArrayHasKey($incomplete->id(), $anomalies);
        self::assertTrue($anomalies[$incomplete->id()]->isOpen());
        self::assertArrayNotHasKey($complete->id(), $anomalies);
    }

    /** @param list<string> $renditionNames */
    private function processedAsset(array $renditionNames): MediaAsset
    {
        $id = new Ulid();
        $asset = new MediaAsset($id, 'originals/'.$id, 'photo.jpg', 'image/jpeg', 1024, sha1((string) $id));
        foreach ($renditionNames as $name) {
            $asset->addRendition(new MediaRendition($asset, $name, 'originals/'.$id.'/renditions/'.$name.'.webp', 100, 50, 64));
        }
        $asset->markProcessed();

        return $asset;
    }

    private function photo(string $assetId): RessourceLieu
    {
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($assetId);
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');

        return $resource;
    }
}
