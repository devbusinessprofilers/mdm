<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\PruneMarketplacePhotos;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\MessageHandler\RemoveFicheFromMarketplaceHandler;
use App\Etl\MessageHandler\SyncFicheMarketplaceHandler;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Repository\LieuRepository;
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
        self::assertSame(['GENERALE_TYPOLOGIE_3'], $payload['data']['typologie']);
        self::assertSame('08:30', $payload['data']['horaires']['ouverture']);
        self::assertNull($payload['data']['horaires']['fermeture']);
        self::assertSame('120.50', $payload['data']['tarifs']['seminaire']['journeeEtude']);
        self::assertSame('12345678900012', $payload['data']['administratif']['infoLegale']['siret']);
        self::assertSame('gare', $payload['data']['acces'][0]['type']);
        self::assertSame('Gare de Lyon', $payload['data']['acces'][0]['nom']);
        self::assertSame('2.50', $payload['data']['acces'][0]['distanceKilometres']);
        self::assertSame('Fermeture annuelle', $payload['data']['periodesFermeture'][0]['nom']);
        self::assertSame('2026-08-01', $payload['data']['periodesFermeture'][0]['dateDebut']);
        self::assertSame('2026-08-15', $payload['data']['periodesFermeture'][0]['dateFin']);
        self::assertSame('English description', $payload['translations']['en']['description']);
        self::assertSame([], $payload['photos']);
        self::assertNull($payload['data']['restaurantAssocie']);
        self::assertNotEmpty($payload['sequence']);

        $tracked = $this->tracking()->forFiche($fiche->id());
        self::assertNotNull($tracked);
        self::assertSame(MarketplaceSyncStatus::Synced, $tracked->status());
        self::assertSame($payload['sequence'], $tracked->lastSequence());
        // Payload sans photos : une purge de nettoyage suit l'upsert pour
        // retirer d'éventuelles lignes PIM orphelines.
        self::assertSame(1, $this->outboxCount(PruneMarketplacePhotos::class));
    }

    public function testLiaisonLieuRestaurantEstPropageeDansLesPayloads(): void
    {
        $ficheLieu = $this->publishedFiche();
        $lieu = self::getContainer()->get(LieuRepository::class)->findOneByFiche($ficheLieu);
        self::assertInstanceOf(Lieu::class, $lieu);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('La Table des tests');
        $restaurant->changeLieu($lieu);
        $restaurant->fiche()->replaceSiteDiffusion([$this->site]);
        $restaurant->fiche()->publishForImport();
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
        // La liaison est une mise à jour technique côté lieu : sa fiche
        // publiée ne doit pas repasser en cours (sinon dépublication).
        self::assertSame('publiee', $ficheLieu->status()->value);

        $this->handler()(new SyncFicheMarketplace($ficheLieu->idString()));
        $this->handler()(new SyncFicheMarketplace($restaurant->fiche()->idString()));
        $this->entityManager->flush();

        self::assertCount(2, $this->client->upserts);
        $payloadLieu = $this->client->upserts[0]['payload'];
        self::assertSame($restaurant->fiche()->code(), $payloadLieu['data']['restaurantAssocie']['code']);
        self::assertSame('La Table des tests', $payloadLieu['data']['restaurantAssocie']['nom']);
        $payloadRestaurant = $this->client->upserts[1]['payload'];
        self::assertSame($ficheLieu->code(), $payloadRestaurant['data']['lieuAssocie']['code']);
        self::assertSame('Château des tests', $payloadRestaurant['data']['lieuAssocie']['nom']);
    }

    public function testFicheBelowPhotoPolicyIsPrunedAndDepublished(): void
    {
        $fiche = $this->publishedFiche();
        $resource = new RessourceLieu();
        $resource->changeDamAssetId((string) new \Symfony\Component\Uid\Ulid());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_PRINCIPALE');
        $fiche->preserveWorkflowDuring(function () use ($fiche, $resource): void {
            $fiche->addResource($resource);
        });
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->persist($resource);
        $this->entityManager->flush();

        $this->handler()(new SyncFicheMarketplace($fiche->idString()));
        $this->entityManager->flush();

        self::assertSame([], $this->client->upserts);
        self::assertSame(1, $this->outboxCount(PruneMarketplacePhotos::class));
        self::assertSame(1, $this->outboxCount(RemoveFicheFromMarketplace::class));
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
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_3']);
        $lieu->changeDispoHorairesJours(['DISPO_JOUR_OUVERTURE_1' => ['ouverture' => '08:30', 'fermeture' => null]]);
        $lieu->tarification()->changeSeminaireJourneeJourneeEtude('120.50');
        $lieu->administratif()->changeInfoLegaleSiret('12345678900012');
        $acces = new AccesLieu();
        $acces->changeType(TypeAccesLieu::Gare);
        $acces->changeNom('Gare de Lyon');
        $acces->changeDistanceKilometres('2.50');
        $lieu->addAcces($acces);
        $periode = new PeriodeFermeture();
        $periode->changeNom('Fermeture annuelle');
        $periode->changeDateDebut(new \DateTimeImmutable('2026-08-01'));
        $periode->changeDateFin(new \DateTimeImmutable('2026-08-15'));
        $lieu->addPeriodeFermeture($periode);
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
                'enrichment_fiche_translation',
                'etl_fiche_marketplace',
                'pim_fiche_site_diffusion',
                'pim_fiche_attribute_value',
                'pim_ressource_lieu',
                'pim_acces_lieu',
                'pim_periode_fermeture',
                'pim_fiche_administratif',
                'pim_lieu_tarification',
                'pim_restaurant',
                'pim_lieu',
                'pim_fiche',
                'pim_localisation',
            ] as $table
        ) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
