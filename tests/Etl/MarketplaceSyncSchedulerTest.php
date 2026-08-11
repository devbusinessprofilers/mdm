<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class MarketplaceSyncSchedulerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingMarketplaceClient $client;
    private MarketplaceSyncScheduler $scheduler;
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
        $this->scheduler = self::getContainer()->get(MarketplaceSyncScheduler::class);
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

    public function testPublishedFicheWithMarketplaceSiteIsEnqueued(): void
    {
        $fiche = $this->publishedFiche(withSite: true);
        $this->scheduler->schedule($fiche);
        $this->entityManager->flush();

        self::assertSame(1, $this->outboxCount(SyncFicheMarketplace::class));
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testPublishedFicheWithoutTheSiteIsIgnoredWhenUnknown(): void
    {
        $fiche = $this->publishedFiche(withSite: false);
        $this->scheduler->schedule($fiche);
        $this->entityManager->flush();

        self::assertSame(0, $this->outboxCount(SyncFicheMarketplace::class));
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testKnownFicheLosingTheSiteIsRemoved(): void
    {
        $fiche = $this->publishedFiche(withSite: false);
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();

        $this->scheduler->schedule($fiche);
        $this->entityManager->flush();

        self::assertSame(1, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testArchivedKnownFicheIsRemoved(): void
    {
        $fiche = $this->publishedFiche(withSite: true);
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $fiche->archive('tester');
        $this->entityManager->flush();

        $this->scheduler->schedule($fiche);
        $this->entityManager->flush();

        self::assertSame(0, $this->outboxCount(SyncFicheMarketplace::class));
        self::assertSame(1, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testDraftFicheKeepsItsLastPublishedState(): void
    {
        $fiche = $this->publishedFiche(withSite: true);
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $fiche->markChanged();
        $this->entityManager->flush();

        $this->scheduler->schedule($fiche);
        $this->entityManager->flush();

        self::assertSame(0, $this->outboxCount(SyncFicheMarketplace::class));
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    private function publishedFiche(bool $withSite): \App\Pim\Entity\Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        if ($withSite) {
            $fiche->replaceSiteDiffusion([$this->site]);
        }
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
