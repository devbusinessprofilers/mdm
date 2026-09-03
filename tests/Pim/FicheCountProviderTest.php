<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheCountProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[Group('database')]
final class FicheCountProviderTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private FicheRepository $fiches;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fiches = self::getContainer()->get(FicheRepository::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testCountsFichesByTypeAndStatus(): void
    {
        $this->persistLieu(1);
        $this->persistLieu(2);
        $activite = new Activite();
        $activite->changeLabel('Escape game');
        $this->entityManager->persist($activite);
        $this->entityManager->flush();

        $provider = new FicheCountProvider($this->fiches, new ArrayAdapter());

        self::assertSame(2, $provider->totalByType(TypeFiche::Lieu));
        self::assertSame(1, $provider->totalByType(TypeFiche::Activite));
        self::assertSame(0, $provider->totalByType(TypeFiche::Restaurant));
        self::assertSame(2, $provider->countByStatus(TypeFiche::Lieu, StatutFiche::EnCours));
        self::assertSame(0, $provider->countByStatus(TypeFiche::Lieu, StatutFiche::Publiee));
    }

    public function testServesCachedValueUntilExpiry(): void
    {
        $this->persistLieu(1);
        $this->entityManager->flush();

        $provider = new FicheCountProvider($this->fiches, new ArrayAdapter());
        self::assertSame(1, $provider->totalByType(TypeFiche::Lieu));

        $this->persistLieu(2);
        $this->entityManager->flush();

        self::assertSame(1, $provider->totalByType(TypeFiche::Lieu), 'La valeur en cache doit être servie sans recompter.');

        $freshProvider = new FicheCountProvider($this->fiches, new ArrayAdapter());
        self::assertSame(2, $freshProvider->totalByType(TypeFiche::Lieu));
    }

    private function persistLieu(int $code): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel(sprintf('Lieu %d', $code));
        $this->entityManager->persist($lieu);
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
    }
}
