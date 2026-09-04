<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceRetrait;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class MarketplaceRetraitTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testUneFicheDiffuseeEstRetiree(): void
    {
        self::getContainer()->set(MarketplaceClientInterface::class, new RecordingMarketplaceClient());
        $fiche = $this->fichePubliee();
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();

        self::assertTrue($this->service()->retirer($fiche));
        $this->entityManager->flush();
        self::assertSame(1, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testUneFicheInconnueOuDejaRetireeNEstPasRetiree(): void
    {
        self::getContainer()->set(MarketplaceClientInterface::class, new RecordingMarketplaceClient());
        $inconnue = $this->fichePubliee();
        self::assertFalse($this->service()->retirer($inconnue));

        $retiree = $this->fichePubliee();
        $tracked = new FicheMarketplaceSync($retiree->id(), $retiree->code());
        $tracked->recordRemoved('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();
        self::assertFalse($this->service()->retirer($retiree));

        $this->entityManager->flush();
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    public function testSansClientConfigureRienNEstEnfile(): void
    {
        // Client par défaut du conteneur de test : non configuré.
        $fiche = $this->fichePubliee();
        $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
        $tracked->recordSynced('01JOLDSEQ');
        $this->entityManager->persist($tracked);
        $this->entityManager->flush();

        self::assertFalse($this->service()->retirer($fiche));
        $this->entityManager->flush();
        self::assertSame(0, $this->outboxCount(RemoveFicheFromMarketplace::class));
    }

    private function service(): MarketplaceRetrait
    {
        return self::getContainer()->get(MarketplaceRetrait::class);
    }

    private function fichePubliee(): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château du retrait');
        $lieu->fiche()->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $lieu->fiche();
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
        foreach (['outbox_message', 'etl_fiche_marketplace', 'pim_fiche_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation'] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
