<?php

declare(strict_types=1);

namespace App\Tests\Vision;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\PublicMediaUrlGenerator;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\CalculateFicheCompleteness;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Entity\ImageRecognitionSuggestion;
use App\Vision\Enum\RecognitionStatus;
use App\Vision\Message\RecognizeImage;
use App\Vision\MessageHandler\RecognizeImageHandler;
use App\Vision\Repository\ImageRecognitionRepository;
use App\Vision\Service\ImageRecognitionApplier;
use App\Vision\Service\ImageRecognitionManager;
use App\Vision\Service\ImageRecognitionProviderInterface;
use App\Vision\Service\RecognitionResult;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class ImageRecognitionPipelineTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingVisionOutbox $outbox;
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

    public function testManualLaunchQueuesOncePerResourceAndAutoScheduleSkipsActiveOnes(): void
    {
        [$fiche, $media] = $this->ficheWithProcessedPhoto();
        $manager = $this->manager();

        self::assertSame(1, $manager->launchForFiches([$fiche], 'editor'));
        $manager->scheduleForMedia($media, ImageRecognition::CREATED_BY_AUTO);
        $this->entityManager->flush();

        $recognitions = $this->repository()->findAll();
        self::assertCount(1, $recognitions);
        self::assertSame('Prompt reco de test.', $recognitions[0]->prompt());
        self::assertSame('gpt-vision-test', $recognitions[0]->providerModel());
        self::assertCount(1, $this->outbox->ofType(RecognizeImage::class));
    }

    public function testHandlerCreatesSuggestionsFromProviderResult(): void
    {
        [$fiche, $media] = $this->ficheWithProcessedPhoto();
        $manager = $this->manager();
        $manager->launchForFiches([$fiche], 'editor');
        $recognition = $this->repository()->findAll()[0];

        $captured = null;
        $this->handler(function (string $url) use (&$captured): RecognitionResult {
            $captured = $url;

            return new RecognitionResult(
                'Salle de réception au parquet ancien.',
                ['réception', 'parquet'],
                ['vue_type' => 'salle de réception', 'interieur_exterieur' => 'intérieur'],
                ['usage' => ['total_tokens' => 12]],
            );
        })(new RecognizeImage($recognition->id()));

        self::assertIsString($captured);
        self::assertStringContainsString('/photos/large/', $captured);
        self::assertSame(RecognitionStatus::Ready, $recognition->status());
        $paths = array_map(static fn (ImageRecognitionSuggestion $s): string => $s->fieldPath(), $recognition->suggestions()->toArray());
        sort($paths);
        self::assertSame(['extras.interieur_exterieur', 'extras.vue_type', 'keywords', 'legende'], $paths);
    }

    public function testApplierWritesLegendeMergesKeywordsAndSchedulesReindex(): void
    {
        [$fiche] = $this->ficheWithProcessedPhoto(legende: 'Ancienne légende', keywords: 'château, jardin');
        $manager = $this->manager();
        $manager->launchForFiches([$fiche], 'editor');
        $recognition = $this->repository()->findAll()[0];
        $this->handler(static fn (): RecognitionResult => new RecognitionResult(
            str_repeat('Très longue légende. ', 20),
            ['réception', 'jardin'],
            ['vue_type' => 'salle de réception'],
            [],
        ))(new RecognizeImage($recognition->id()));
        $this->entityManager->flush();

        $decisions = [];
        foreach ($recognition->suggestions() as $suggestion) {
            $decisions[$suggestion->id()] = match ($suggestion->fieldPath()) {
                ImageRecognitionSuggestion::PATH_LEGENDE => ['value' => str_repeat('Très longue légende. ', 20), 'accept' => true],
                ImageRecognitionSuggestion::PATH_KEYWORDS => ['value' => 'réception, jardin', 'accept' => true],
                default => ['value' => 'salle de réception', 'accept' => false],
            };
        }
        $this->applier()->apply($recognition, $decisions, 'validator');

        $resource = $recognition->resource();
        self::assertSame(255, mb_strlen((string) $resource->legende()));
        self::assertSame('château, jardin, réception', $resource->keywords());
        self::assertSame(RecognitionStatus::Reviewed, $recognition->status());
        self::assertCount(1, $this->outbox->ofType(IndexFiche::class));
        self::assertCount(1, $this->outbox->ofType(CalculateFicheCompleteness::class));
    }

    public function testApplierWithOnlyRejectionsDoesNotTouchTheFiche(): void
    {
        [$fiche] = $this->ficheWithProcessedPhoto(legende: 'Ancienne légende');
        $manager = $this->manager();
        $manager->launchForFiches([$fiche], 'editor');
        $recognition = $this->repository()->findAll()[0];
        $this->handler(static fn (): RecognitionResult => new RecognitionResult('Nouvelle légende.', [], [], []))(new RecognizeImage($recognition->id()));
        $this->entityManager->flush();

        $decisions = [];
        foreach ($recognition->suggestions() as $suggestion) {
            $decisions[$suggestion->id()] = ['value' => 'Nouvelle légende.', 'accept' => false];
        }
        $this->applier()->apply($recognition, $decisions, 'validator');

        self::assertSame('Ancienne légende', $recognition->resource()->legende());
        self::assertSame(RecognitionStatus::Reviewed, $recognition->status());
        self::assertCount(0, $this->outbox->ofType(IndexFiche::class));
    }

    private function manager(): ImageRecognitionManager
    {
        return new ImageRecognitionManager(
            $this->repository(),
            self::getContainer()->get(RessourceLieuRepository::class),
            self::getContainer()->get(MediaAssetRepository::class),
            $this->parametres,
            $this->outbox,
            $this->entityManager,
        );
    }

    /** @param \Closure(string): RecognitionResult $describe */
    private function handler(\Closure $describe): RecognizeImageHandler
    {
        $provider = new class($describe) implements ImageRecognitionProviderInterface {
            public function __construct(private readonly \Closure $describe)
            {
            }

            public function describe(string $imageUrl, string $prompt, string $model): RecognitionResult
            {
                return ($this->describe)($imageUrl);
            }
        };

        return new RecognizeImageHandler(
            $this->repository(),
            $provider,
            self::getContainer()->get(PublicMediaUrlGenerator::class),
            $this->entityManager,
        );
    }

    private function applier(): ImageRecognitionApplier
    {
        return new ImageRecognitionApplier($this->outbox, $this->entityManager);
    }

    private function repository(): ImageRecognitionRepository
    {
        return self::getContainer()->get(ImageRecognitionRepository::class);
    }

    /** @return array{0: Fiche, 1: MediaAsset} */
    private function ficheWithProcessedPhoto(?string $legende = null, ?string $keywords = null): array
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        $id = new Ulid();
        $asset = new MediaAsset($id, 'dev/photos/originals/'.$id.'/original.jpg', 'original.jpg', 'image/jpeg', 1024, hash('sha256', (string) $id));
        $asset->addRendition(new MediaRendition($asset, 'large', 'dev/photos/large/'.$id.'.webp', 960, 480, 64));
        $asset->markProcessed();
        $this->entityManager->persist($asset);
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $resource->changeLegende($legende);
        $resource->changeKeywords($keywords);
        $fiche->addResource($resource);
        $this->entityManager->persist($resource);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return [$fiche, $asset];
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
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
