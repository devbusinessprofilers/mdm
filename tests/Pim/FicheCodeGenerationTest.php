<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Kernel;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('database')]
final class FicheCodeGenerationTest extends TestCase
{
    private Kernel $kernel;
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    /** @var list<object> */
    private array $createdEntities = [];

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        $this->kernel = new Kernel((string) ($_SERVER['APP_ENV'] ?? 'test'), true);
        $this->kernel->boot();
        $doctrine = $this->kernel->getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $connection = $doctrine->getConnection();
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $entityManager = $doctrine->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            foreach ($this->createdEntities as $entity) {
                if ($this->entityManager->contains($entity)) {
                    $this->entityManager->remove($entity);
                }
            }
            $this->entityManager->flush();
        }

        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }

        parent::tearDown();
    }

    public function testCodesAreGlobalConsecutiveAndNotReusedAfterDeletion(): void
    {
        $lieu = new Lieu();
        $this->persistAndFlush($lieu);

        $activite = new Activite();
        $this->persistAndFlush($activite);

        $restaurant = new Restaurant();
        $this->persistAndFlush($restaurant);

        $service = new ServiceEvenementiel();
        $this->persistAndFlush($service);

        self::assertSame(
            range($lieu->code(), $lieu->code() + 3),
            [
                $lieu->code(),
                $activite->code(),
                $restaurant->code(),
                $service->code(),
            ],
        );

        $deletedCode = $activite->code();
        $this->entityManager->remove($activite);
        $this->entityManager->flush();

        $nextLieu = new Lieu();
        $this->persistAndFlush($nextLieu);

        self::assertSame($service->code() + 1, $nextLieu->code());
        self::assertNotSame($deletedCode, $nextLieu->code());
    }

    public function testPersistedCodeCannotBeUpdated(): void
    {
        $lieu = new Lieu();
        $this->persistAndFlush($lieu);

        $this->expectException(DbalException::class);
        $this->connection->executeStatement(
            'UPDATE pim_fiche SET code = :newCode WHERE code = :currentCode',
            [
                'newCode' => $lieu->code() + 100,
                'currentCode' => $lieu->code(),
            ],
        );
    }

    private function persistAndFlush(object $entity): void
    {
        $this->createdEntities[] = $entity;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
