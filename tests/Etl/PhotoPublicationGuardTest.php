<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\PhotoPublicationGuard;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class PhotoPublicationGuardTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private PhotoPublicationGuard $guard;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        self::getContainer()->set(MarketplaceClientInterface::class, new RecordingMarketplaceClient());
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->guard = self::getContainer()->get(PhotoPublicationGuard::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testCompliantPublishedFicheIsUntouched(): void
    {
        $fiche = $this->publishedLieuFiche(photos: 4, withPrincipale: true);

        self::assertFalse($this->guard->enforce($fiche));
        self::assertSame(StatutFiche::Publiee, $fiche->status());
    }

    public function testPublishedFicheBelowMinimumIsDemotedAndRemoved(): void
    {
        $fiche = $this->publishedLieuFiche(photos: 3, withPrincipale: true);
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();

        self::assertTrue($this->guard->enforce($fiche));
        $this->entityManager->flush();

        self::assertSame(StatutFiche::EnCours, $fiche->status());
        self::assertSame(1, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testPublishedFicheWithoutAnyPhotoIsDemotedDespiteLegacyImagery(): void
    {
        // Contrairement à la politique de diffusion, pas d'exemption
        // « imagerie legacy » : zéro photo n'est pas conforme.
        $fiche = $this->publishedLieuFiche(photos: 0);

        self::assertTrue($this->guard->enforce($fiche));
        $this->entityManager->flush();
        self::assertSame(StatutFiche::EnCours, $fiche->status());
        // Fiche inconnue de la marketplace : rien à retirer.
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testAlreadyRemovedFicheIsNotRemovedTwice(): void
    {
        $fiche = $this->publishedLieuFiche(photos: 3, withPrincipale: true);
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordRemoved('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();

        self::assertTrue($this->guard->enforce($fiche));
        $this->entityManager->flush();
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testDraftFicheIsIgnored(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Brouillon sans photos');
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        self::assertFalse($this->guard->enforce($lieu->fiche()));
        self::assertSame(StatutFiche::EnCours, $lieu->fiche()->status());
    }

    private function publishedLieuFiche(int $photos, bool $withPrincipale = false): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $fiche = $lieu->fiche();
        for ($i = 0; $i < $photos; ++$i) {
            $resource = new RessourceLieu();
            $resource->changeDamAssetId((string) new Ulid());
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeUsage(0 === $i && $withPrincipale ? 'PHOTO_PRINCIPALE' : 'PHOTO_DIVERSE');
            $resource->changePosition($i + 1);
            $fiche->addResource($resource);
        }
        $fiche->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $fiche;
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
                'pim_ressource_lieu',
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
