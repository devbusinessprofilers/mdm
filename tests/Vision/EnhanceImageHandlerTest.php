<?php

declare(strict_types=1);

namespace App\Tests\Vision;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\NatureRessource;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Message\EnhanceImage;
use App\Vision\MessageHandler\EnhanceImageHandler;
use App\Vision\Repository\ImageEnhancementRepository;
use App\Vision\Service\EnhancedImageResult;
use App\Vision\Service\ImageEnhancementProviderInterface;
use App\Vision\Service\ImageMagickEnhancementProvider;
use App\Vision\Service\OpenAiProviderException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class EnhanceImageHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingVisionStorage $storage;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->storage = new RecordingVisionStorage();
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testSuccessfulEnhancementStoresThePrivateCandidate(): void
    {
        $enhancement = $this->queuedEnhancement('contenu-original');
        $handler = $this->handler(static fn (): EnhancedImageResult => new EnhancedImageResult('contenu-retouche', 'image/png', ['usage' => ['total_tokens' => 9]]));

        $handler(new EnhanceImage($enhancement->id()));

        self::assertSame(EnhancementStatus::Ready, $enhancement->status());
        $expectedKey = \dirname($enhancement->media()->originalStorageKey()).'/retouche/'.$enhancement->id().'.png';
        self::assertSame($expectedKey, $enhancement->resultStorageKey());
        self::assertSame(hash('sha256', 'contenu-retouche'), $enhancement->resultChecksum());
        self::assertSame('contenu-retouche', $this->storage->written[$expectedKey]);
        self::assertSame(['usage' => ['total_tokens' => 9]], $enhancement->rawResponse());
        self::assertSame(1, $enhancement->attempts());
    }

    public function testStaleChecksumFailsTheJobWithoutCallingTheProvider(): void
    {
        $enhancement = $this->queuedEnhancement('contenu-reuploade');
        $handler = $this->handler(static function (): EnhancedImageResult {
            throw new \LogicException('Le fournisseur ne doit pas être appelé sur une empreinte périmée.');
        });
        $this->connection->executeStatement('UPDATE vision_image_enhancement SET source_checksum = ?', [str_repeat('f', 64)]);
        $this->entityManager->refresh($enhancement);

        $handler(new EnhanceImage($enhancement->id()));

        self::assertSame(EnhancementStatus::Failed, $enhancement->status());
        self::assertStringContainsString('empreinte', (string) $enhancement->errorMessage());
    }

    public function testRetryableProviderErrorBubblesAsRecoverableAndPermanentErrorFailsTheJob(): void
    {
        $retryable = $this->queuedEnhancement('contenu-a');
        $handler = $this->handler(static function (): EnhancedImageResult {
            throw new OpenAiProviderException('OpenAI indisponible.', true, 30);
        });
        try {
            $handler(new EnhanceImage($retryable->id()));
            self::fail('Une erreur retryable doit remonter au transport.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(30000, $error->getRetryDelay());
        }

        $permanent = $this->queuedEnhancement('contenu-b');
        $handler = $this->handler(static function (): EnhancedImageResult {
            throw new OpenAiProviderException('Requête refusée.', false);
        });
        $handler(new EnhanceImage($permanent->id()));
        self::assertSame(EnhancementStatus::Failed, $permanent->status());
        self::assertSame('Requête refusée.', $permanent->errorMessage());
    }

    /** @param \Closure(): EnhancedImageResult $enhance */
    private function handler(\Closure $enhance): EnhanceImageHandler
    {
        $provider = new class($enhance) implements ImageEnhancementProviderInterface {
            public function __construct(private readonly \Closure $enhance)
            {
            }

            public function enhance(string $imagePath, string $mimeType, string $prompt, string $model): EnhancedImageResult
            {
                return ($this->enhance)();
            }
        };

        return new EnhanceImageHandler(
            self::getContainer()->get(ImageEnhancementRepository::class),
            $provider,
            new ImageMagickEnhancementProvider(),
            $this->storage,
        );
    }

    private function queuedEnhancement(string $originalContents): ImageEnhancement
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        $id = new Ulid();
        $key = 'dev/photos/originals/'.$id.'/original.jpg';
        $asset = new MediaAsset($id, $key, 'original.jpg', 'image/jpeg', strlen($originalContents), hash('sha256', $originalContents));
        $asset->markProcessed();
        $this->storage->written[$key] = $originalContents;
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $fiche->addResource($resource);
        $enhancement = new ImageEnhancement($fiche, $asset, $resource, 'Prompt de test.', 'gpt-image-test', 'editor');
        $this->entityManager->persist($asset);
        $this->entityManager->persist($resource);
        $this->entityManager->persist($lieu);
        $this->entityManager->persist($enhancement);
        $this->entityManager->flush();

        return $enhancement;
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
