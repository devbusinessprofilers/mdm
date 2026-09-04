<?php

declare(strict_types=1);

namespace App\Tests\Vision;

use App\Dam\Entity\MediaAsset;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementProvider;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Message\ApplyImageEnhancement;
use App\Vision\Message\EnhanceImage;
use App\Vision\Repository\ImageEnhancementRepository;
use App\Vision\Service\ImageEnhancementManager;
use App\Vision\Service\ImageMagickEnhancementProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class ImageEnhancementManagerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingVisionOutbox $outbox;
    private RecordingVisionStorage $storage;
    private StubVisionParametres $parametres;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->outbox = new RecordingVisionOutbox();
        $this->storage = new RecordingVisionStorage();
        $this->parametres = new StubVisionParametres();
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testLaunchQueuesProcessedPhotosOnceWithSnapshotAndSkipsActiveJobs(): void
    {
        $fiche = $this->ficheWithPhotos();
        $manager = $this->manager();

        self::assertSame(1, $manager->launchForFiches([$fiche], 'editor'));
        self::assertSame(0, $manager->launchForFiches([$fiche], 'editor'));

        $enhancements = $this->repository()->findAll();
        self::assertCount(1, $enhancements);
        $enhancement = $enhancements[0];
        self::assertSame(EnhancementStatus::Queued, $enhancement->status());
        self::assertSame('Prompt de test.', $enhancement->prompt());
        self::assertSame('gpt-image-test', $enhancement->providerModel());
        self::assertSame('editor', $enhancement->createdBy());
        self::assertCount(1, $this->outbox->ofType(EnhanceImage::class));
    }

    public function testLaunchIsRefusedWhenOpenAiIsDisabled(): void
    {
        $this->parametres->actif = false;
        $this->expectException(\DomainException::class);
        $this->manager()->launchForFiches([$this->ficheWithPhotos()], 'editor');
    }

    public function testImageMagickLaunchIgnoresOpenAiGateAndRecordsProvider(): void
    {
        $this->parametres->actif = false;
        $manager = $this->manager();

        self::assertSame(1, $manager->launchForFiches([$this->ficheWithPhotos()], 'editor', EnhancementProvider::ImageMagick));

        $enhancement = $this->repository()->findAll()[0];
        self::assertSame(EnhancementProvider::ImageMagick, $enhancement->provider());
        self::assertSame(ImageMagickEnhancementProvider::MODEL, $enhancement->providerModel());
        self::assertSame(ImageMagickEnhancementProvider::DESCRIPTION, $enhancement->prompt());
        self::assertCount(1, $this->outbox->ofType(EnhanceImage::class));
    }

    public function testAcceptAppliesEnhancedSourceResetsCropAndSchedulesRegeneration(): void
    {
        $fiche = $this->ficheWithPhotos();
        $manager = $this->manager();
        $manager->launchForFiches([$fiche], 'editor');
        $enhancement = $this->readyEnhancement();

        $manager->accept($enhancement->id(), 'validator');

        $media = $enhancement->media();
        self::assertTrue($media->isEnhanced());
        self::assertSame('dev/photos/originals/x/retouche/'.$enhancement->id().'.png', $media->sourceStorageKey());
        self::assertSame(str_repeat('c', 64), $media->sourceChecksum());
        $resource = $enhancement->resource();
        self::assertNotNull($resource);
        self::assertNull($resource->crop());
        self::assertSame(0, $resource->rotation());
        self::assertSame(EnhancementStatus::Accepted, $enhancement->status());
        self::assertCount(1, $this->outbox->ofType(ApplyImageEnhancement::class));
    }

    public function testRejectPurgesTheCandidateObject(): void
    {
        $fiche = $this->ficheWithPhotos();
        $manager = $this->manager();
        $manager->launchForFiches([$fiche], 'editor');
        $enhancement = $this->readyEnhancement();

        $manager->reject($enhancement->id(), 'validator');

        self::assertSame(EnhancementStatus::Rejected, $enhancement->status());
        self::assertSame(['dev/photos/originals/x/retouche/'.$enhancement->id().'.png'], $this->storage->deleted);
        self::assertCount(0, $this->outbox->ofType(ApplyImageEnhancement::class));
    }

    public function testRevertRestoresTheOriginalAndSchedulesRegeneration(): void
    {
        $fiche = $this->ficheWithPhotos();
        $manager = $this->manager();
        $manager->launchForFiches([$fiche], 'editor');
        $enhancement = $this->readyEnhancement();
        $manager->accept($enhancement->id(), 'validator');

        $manager->revert($enhancement->media()->id());

        self::assertFalse($enhancement->media()->isEnhanced());
        self::assertCount(2, $this->outbox->ofType(ApplyImageEnhancement::class));
        $this->expectException(\DomainException::class);
        $manager->revert($enhancement->media()->id());
    }

    private function manager(): ImageEnhancementManager
    {
        return new ImageEnhancementManager(
            $this->repository(),
            self::getContainer()->get(RessourceLieuRepository::class),
            self::getContainer()->get(MediaAssetRepository::class),
            $this->parametres,
            $this->outbox,
            $this->storage,
            $this->entityManager,
            new NullLogger(),
        );
    }

    private function repository(): ImageEnhancementRepository
    {
        return self::getContainer()->get(ImageEnhancementRepository::class);
    }

    private function readyEnhancement(): ImageEnhancement
    {
        $enhancement = $this->repository()->findAll()[0];
        $enhancement->start();
        $enhancement->complete(
            'dev/photos/originals/x/retouche/'.$enhancement->id().'.png',
            str_repeat('c', 64),
            2048,
            ['usage' => ['total_tokens' => 5]],
        );
        $this->entityManager->flush();

        return $enhancement;
    }

    /** Fiche Lieu avec une photo traitée (recadrée) et une photo encore en file. */
    private function ficheWithPhotos(): \App\Pim\Entity\Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        foreach ([true, false] as $processed) {
            $id = new Ulid();
            $asset = new MediaAsset($id, 'dev/photos/originals/x/'.$id.'.jpg', 'photo.jpg', 'image/jpeg', 1024, sha1((string) $id));
            if ($processed) {
                $asset->markProcessed();
            }
            $this->entityManager->persist($asset);
            $resource = new RessourceLieu();
            $resource->changeDamAssetId($asset->id());
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeUsage('PHOTO_DIVERSE');
            if ($processed) {
                $resource->changeCrop(10, 10, 200, 100);
                $resource->changeRotation(90);
            }
            $fiche->addResource($resource);
            $this->entityManager->persist($resource);
        }
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $fiche;
    }

    private function clear(): void
    {
        foreach ([
            'vision_image_recognition_suggestion',
            'vision_image_recognition',
            'vision_image_enhancement',
            'outbox_message',
            'pim_ressource_lieu',
            'dam_media_rendition',
            'dam_media_asset',
            'pim_fiche_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}

final class RecordingVisionOutbox implements OutboxPublisherInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function enqueue(object $message): string
    {
        $this->messages[] = $message;

        return (string) new Ulid();
    }

    /**
     * @param class-string $type
     *
     * @return list<object>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->messages, static fn (object $message): bool => $message instanceof $type));
    }
}

final class StubVisionParametres implements ParametreProviderInterface
{
    public bool $actif = true;

    public function bool(string $nom): bool
    {
        return match ($nom) {
            'openai.actif' => $this->actif,
            default => false,
        };
    }

    public function int(string $nom): int
    {
        return 0;
    }

    public function string(string $nom): string
    {
        return match ($nom) {
            'openai.retouche_prompt' => 'Prompt de test.',
            'openai.retouche_modele' => 'gpt-image-test',
            'openai.reco_prompt' => 'Prompt reco de test.',
            'openai.reco_modele' => 'gpt-vision-test',
            default => '',
        };
    }
}

final class RecordingVisionStorage implements PrivateObjectStorageInterface
{
    /** @var list<string> */
    public array $deleted = [];

    /** @var array<string, string> */
    public array $written = [];

    public function write(string $key, string $contents, array $options = []): void
    {
        $this->written[$key] = $contents;
    }

    public function writeStream(string $key, mixed $stream, array $options = []): void
    {
        $this->written[$key] = (string) stream_get_contents($stream);
    }

    public function read(string $key): string
    {
        return $this->written[$key] ?? throw new \RuntimeException('Objet inconnu : '.$key);
    }

    public function readStream(string $key): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        if (false === $stream) {
            throw new \RuntimeException('Flux temporaire indisponible.');
        }
        fwrite($stream, $this->read($key));
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return isset($this->written[$key]);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
    {
        return 'https://private.test/'.$key;
    }

    public function delete(string $key): void
    {
        $this->deleted[] = $key;
        unset($this->written[$key]);
    }

    public function deleteDirectory(string $prefix): void
    {
    }
}
