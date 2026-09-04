<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\PruneMarketplacePhotos;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\MessageHandler\PruneMarketplacePhotosHandler;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class PruneMarketplacePhotosHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingMarketplaceClient $client;
    private SiteDiffusion $site;

    /** @var list<RessourceLieu> */
    private array $resources = [];

    /** @var list<string> */
    private array $locations = [];

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->client = new RecordingMarketplaceClient();
        self::getContainer()->set(MarketplaceClientInterface::class, $this->client);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
        $this->site = $this->marketplaceSite();
        $this->resources = [];
        $this->locations = [];
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testDeletedPhotoIsPrunedAndSequenceRecorded(): void
    {
        $fiche = $this->draftedFicheWithPhotos(5);
        $tracked = $this->track($fiche);
        $this->deletePhoto(3);
        $this->client->pruneResult = ['removed' => 1, 'remaining' => 4, 'principaleRemaining' => true];

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));
        $this->entityManager->flush();

        self::assertCount(1, $this->client->prunes);
        $prune = $this->client->prunes[0];
        self::assertSame($fiche->code(), $prune['code']);
        $expected = $this->locations;
        unset($expected[3]);
        self::assertSame(array_values($expected), $prune['locations']);
        self::assertSame($prune['sequence'], $tracked->lastSequence());
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testFicheIsDepublishedWhenBelowMinimum(): void
    {
        $fiche = $this->draftedFicheWithPhotos(4);
        $this->track($fiche);
        $this->deletePhoto(2);
        $this->client->pruneResult = ['removed' => 1, 'remaining' => 3, 'principaleRemaining' => true];

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));
        $this->entityManager->flush();

        self::assertSame(1, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testMissingRemoteMainAloneDoesNotDepublish(): void
    {
        // La principale est la première photo de l'ordre : le drapeau `main`
        // du snapshot distant n'entre plus en compte — tant qu'il reste assez
        // de photos, le prochain sync complet repousse la nouvelle principale.
        $fiche = $this->draftedFicheWithPhotos(5);
        $this->track($fiche);
        $this->deletePhoto(0);
        $this->client->pruneResult = ['removed' => 1, 'remaining' => 4, 'principaleRemaining' => false];

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));
        $this->entityManager->flush();

        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testLegacyFicheWithoutPimPhotosIsNotDepublished(): void
    {
        $fiche = $this->draftedFicheWithPhotos(0);
        $this->track($fiche);
        $this->client->pruneResult = ['removed' => 0, 'remaining' => 0, 'principaleRemaining' => false];

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));
        $this->entityManager->flush();

        self::assertCount(1, $this->client->prunes);
        self::assertSame([], $this->client->prunes[0]['locations']);
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testUntrackedFicheIsSkipped(): void
    {
        $fiche = $this->draftedFicheWithPhotos(5);

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));

        self::assertSame([], $this->client->prunes);
    }

    public function testStaleSequenceKeepsLocalState(): void
    {
        $fiche = $this->draftedFicheWithPhotos(5);
        $tracked = $this->track($fiche);
        $this->deletePhoto(3);
        $this->client->pruneResult = false;

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));
        $this->entityManager->flush();

        self::assertSame('01JOLDSEQ', $tracked->lastSequence());
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testUnknownFicheOnMarketplaceIsIgnored(): void
    {
        $fiche = $this->draftedFicheWithPhotos(5);
        $tracked = $this->track($fiche);
        $this->deletePhoto(3);
        $this->client->pruneResult = null;

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));
        $this->entityManager->flush();

        self::assertSame('01JOLDSEQ', $tracked->lastSequence());
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testRepublishedConformFicheIsLeftToSync(): void
    {
        $fiche = $this->draftedFicheWithPhotos(4, draft: false);
        $this->track($fiche);

        $this->handler()(new PruneMarketplacePhotos($fiche->idString()));

        self::assertSame([], $this->client->prunes);
    }

    private function handler(): PruneMarketplacePhotosHandler
    {
        return self::getContainer()->get(PruneMarketplacePhotosHandler::class);
    }

    private function track(Fiche $fiche): FicheMarketplaceSync
    {
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();

        return $tracked;
    }

    /**
     * Fiche Lieu publiée puis repassée en cours (sauf $draft = false), avec
     * $count photos traitées : la première porte l'usage PHOTO_PRINCIPALE.
     */
    private function draftedFicheWithPhotos(int $count, bool $draft = true): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        $fiche->replaceSiteDiffusion([$this->site]);
        for ($i = 0; $i < $count; ++$i) {
            $id = new Ulid();
            $asset = new MediaAsset($id, $this->prefix().'photos/originals/'.$id, 'photo'.$i.'.jpg', 'image/jpeg', 1024, sha1((string) $id));
            $location = 'fiche-test/photo-'.$i.'-'.$id.'.webp';
            $asset->addRendition(new MediaRendition($asset, 'large', $this->prefix().'photos/large/'.$location, 960, 480, 64));
            $asset->markProcessed();
            $this->entityManager->persist($asset);
            $resource = new RessourceLieu();
            $resource->changeDamAssetId($asset->id());
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeUsage(0 === $i ? 'PHOTO_PRINCIPALE' : 'PHOTO_DIVERSE');
            $resource->changePosition($i + 1);
            $fiche->addResource($resource);
            $this->entityManager->persist($resource);
            $this->resources[] = $resource;
            $this->locations[] = $location;
        }
        $fiche->publishForImport();
        if ($draft) {
            $fiche->markChanged();
        }
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $fiche;
    }

    private function deletePhoto(int $index): void
    {
        $resource = $this->resources[$index];
        $resource->fiche()?->removeResource($resource);
        $this->entityManager->remove($resource);
        $this->entityManager->flush();
    }

    private function prefix(): string
    {
        $prefix = trim((string) ($_ENV['S3_PREFIX'] ?? getenv('S3_PREFIX') ?: ''), '/');

        return '' === $prefix ? '' : $prefix.'/';
    }

    private function marketplaceSite(): SiteDiffusion
    {
        /** @var SiteDiffusionRepository $sites */
        $sites = self::getContainer()->get(SiteDiffusionRepository::class);
        $site = $sites->findOneByCode(MarketplaceSyncScheduler::SITE_CODE);
        if (null === $site) {
            $site = new SiteDiffusion(MarketplaceSyncScheduler::SITE_CODE, 'Marketplace Business Profilers', 'Business Profilers');
            $this->entityManager->persist($site);
            $this->entityManager->flush();
        }

        return $site;
    }

    private function outboxCount(string $messageType): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
            [$messageType],
        );
    }

    private function clear(): void
    {
        foreach (
            [
                'outbox_message',
                'etl_fiche_marketplace',
                'pim_fiche_site_diffusion',
                'pim_ressource_lieu',
                'dam_media_rendition',
                'dam_media_asset',
                'pim_fiche_administratif',
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
