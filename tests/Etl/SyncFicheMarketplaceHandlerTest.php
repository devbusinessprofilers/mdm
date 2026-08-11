<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\MessageHandler\RemoveFicheFromMarketplaceHandler;
use App\Etl\MessageHandler\SyncFicheMarketplaceHandler;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class SyncFicheMarketplaceHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingMarketplaceClient $client;
    private SiteDiffusion $site;

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
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testSnapshotIsPushedAndTracked(): void
    {
        $fiche = $this->publishedFiche();
        $translation = new FicheTranslation($fiche, 'lieu.descGenerale', 'Description générale', SupportedLocale::En, 'Description française');
        $translation->applyManual('English description', 'tester');
        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $this->handler()(new SyncFicheMarketplace($fiche->idString()));
        $this->entityManager->flush();

        self::assertCount(1, $this->client->upserts);
        $payload = $this->client->upserts[0]['payload'];
        self::assertSame($fiche->code(), $this->client->upserts[0]['code']);
        self::assertSame('lieu', $payload['type']);
        self::assertSame('Château des tests', $payload['data']['nom']);
        self::assertSame('Paris', $payload['data']['adresse']['ville']);
        self::assertSame('English description', $payload['translations']['en']['description']);
        self::assertSame([], $payload['photos']);
        self::assertNotEmpty($payload['sequence']);

        $tracked = $this->tracking()->forFiche($fiche->id());
        self::assertNotNull($tracked);
        self::assertSame(MarketplaceSyncStatus::Synced, $tracked->status());
        self::assertSame($payload['sequence'], $tracked->lastSequence());
    }

    public function testStaleMessageForUnpublishedFicheIsSkipped(): void
    {
        $fiche = $this->publishedFiche();
        $fiche->markChanged();
        $this->entityManager->flush();

        $this->handler()(new SyncFicheMarketplace($fiche->idString()));

        self::assertSame([], $this->client->upserts);
        self::assertNull($this->tracking()->forFiche($fiche->id()));
    }

    public function testRemovalDepublishesAndTracks(): void
    {
        $fiche = $this->publishedFiche();
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $fiche->archive('tester');
        $this->entityManager->flush();

        $this->removeHandler()(new RemoveFicheFromMarketplace($fiche->idString()));
        $this->entityManager->flush();

        self::assertCount(1, $this->client->removals);
        self::assertSame($fiche->code(), $this->client->removals[0]['code']);
        self::assertSame(MarketplaceSyncStatus::Removed, $tracked->status());
    }

    public function testRemovalWithoutPriorSyncIsSkipped(): void
    {
        $fiche = $this->publishedFiche();
        $fiche->archive('tester');
        $this->entityManager->flush();

        $this->removeHandler()(new RemoveFicheFromMarketplace($fiche->idString()));

        self::assertSame([], $this->client->removals);
    }

    private function handler(): SyncFicheMarketplaceHandler
    {
        return self::getContainer()->get(SyncFicheMarketplaceHandler::class);
    }

    private function removeHandler(): RemoveFicheFromMarketplaceHandler
    {
        return self::getContainer()->get(RemoveFicheFromMarketplaceHandler::class);
    }

    private function tracking(): FicheMarketplaceSyncRepository
    {
        return self::getContainer()->get(FicheMarketplaceSyncRepository::class);
    }

    private function publishedFiche(): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        $fiche->replaceSiteDiffusion([$this->site]);
        $fiche->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $fiche;
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

    private function clear(): void
    {
        foreach (
            [
                'outbox_message',
                'enrichment_fiche_translation',
                'etl_fiche_marketplace',
                'pim_fiche_site_diffusion',
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
