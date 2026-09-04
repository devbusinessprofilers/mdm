<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\LocalisationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class FicheRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private FicheRepository $repository;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(FicheRepository::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testFindAllListPageListsEveryTypeSortedAndPaginated(): void
    {
        $lieu = $this->createLieu('Palais Alpha', 'Paris', 'FR');
        $activite = new Activite();
        $activite->changeLabel('Croisière Beta');
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot Gamma');
        $restaurant->changeLocalisation($this->localisation('Lyon', 'FR'));
        $lieu2 = $this->createLieu('Palais Delta', 'Rome', 'IT');
        $orphan = new Fiche(TypeFiche::Lieu);
        foreach ([$activite, $restaurant, $orphan] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $expectedIds = [
            $lieu->id(),
            $activite->id(),
            $restaurant->id(),
            $lieu2->id(),
            $orphan->idString(),
        ];

        $fullPage = $this->repository->findAllListPage();
        self::assertCount(5, $fullPage->items);
        self::assertNull($fullPage->nextCursor);
        self::assertEqualsCanonicalizing($expectedIds, array_map(static fn ($item): string => $item->id, $fullPage->items));
        $sorted = $fullPage->items;
        usort($sorted, static fn ($a, $b): int => [$b->updatedAt, $b->id] <=> [$a->updatedAt, $a->id]);
        self::assertSame(
            array_map(static fn ($item): string => $item->id, $sorted),
            array_map(static fn ($item): string => $item->id, $fullPage->items),
        );

        $orphanItem = null;
        foreach ($fullPage->items as $item) {
            if ($item->id === $orphan->idString()) {
                $orphanItem = $item;
            }
        }
        self::assertNotNull($orphanItem);
        self::assertSame(0, $orphanItem->completeness);

        $pagedIds = [];
        $cursor = null;
        do {
            $page = $this->repository->findAllListPage(FicheCursor::decode($cursor), 2);
            $pagedIds = [...$pagedIds, ...array_map(static fn ($item): string => $item->id, $page->items)];
            $cursor = $page->nextCursor;
        } while (null !== $cursor);
        self::assertCount(5, $pagedIds);
        self::assertCount(5, array_unique($pagedIds));
        self::assertEqualsCanonicalizing($expectedIds, $pagedIds);
    }

    public function testFindAllListPageAppliesEveryFilter(): void
    {
        $lieu = $this->createLieu('Palais Alpha', 'Paris', 'FR');
        $lieu->fiche()->publishForImport();
        $lieu2 = $this->createLieu('Palais Delta', 'Rome', 'IT');
        $activite = new Activite();
        $activite->changeLabel('Croisière Beta');
        $this->entityManager->persist($activite);
        $this->entityManager->flush();
        $this->setLieuCompleteness($lieu->id(), 80);
        $this->setLieuCompleteness($lieu2->id(), 30);

        $typePage = $this->repository->findAllListPage(type: TypeFiche::Activite);
        self::assertSame([$activite->id()], array_map(static fn ($item): string => $item->id, $typePage->items));

        $statusPage = $this->repository->findAllListPage(status: StatutFiche::Publiee);
        self::assertSame([$lieu->id()], array_map(static fn ($item): string => $item->id, $statusPage->items));

        $countryPage = $this->repository->findAllListPage(countryCode: 'IT');
        self::assertSame([$lieu2->id()], array_map(static fn ($item): string => $item->id, $countryPage->items));

        $minPage = $this->repository->findAllListPage(completenessMin: 50);
        self::assertSame([$lieu->id()], array_map(static fn ($item): string => $item->id, $minPage->items));

        $maxPage = $this->repository->findAllListPage(completenessMax: 40);
        self::assertEqualsCanonicalizing(
            [$lieu2->id(), $activite->id()],
            array_map(static fn ($item): string => $item->id, $maxPage->items),
        );

        $combinedPage = $this->repository->findAllListPage(
            type: TypeFiche::Lieu,
            countryCode: 'FR',
            completenessMin: 50,
            completenessMax: 100,
        );
        self::assertSame([$lieu->id()], array_map(static fn ($item): string => $item->id, $combinedPage->items));

        $emptyPage = $this->repository->findAllListPage(countryCode: 'FR', completenessMax: 40);
        self::assertSame([], $emptyPage->items);
    }

    public function testFindDistinctCountryCodesIsSortedAndDeduplicated(): void
    {
        $this->createLieu('Palais Alpha', 'Paris', 'FR');
        $this->createLieu('Palais Beta', 'Lyon', 'FR');
        $this->createLieu('Palais Gamma', 'Rome', 'IT');
        $this->createLieu('Palais Delta', 'Berlin', null);

        $codes = self::getContainer()->get(LocalisationRepository::class)->findDistinctCountryCodes();

        self::assertSame(['FR', 'IT'], $codes);
    }

    private function createLieu(string $label, string $ville, ?string $countryCode): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $lieu->changeLocalisation($this->localisation($ville, $countryCode));
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $lieu;
    }

    private function localisation(string $ville, ?string $countryCode): Localisation
    {
        $localisation = new Localisation();
        $localisation->changeVille($ville);
        $localisation->changeCountryCode($countryCode);

        return $localisation;
    }

    private function setLieuCompleteness(string $lieuId, int $completeness): void
    {
        $this->connection->executeStatement(
            'UPDATE pim_lieu SET completeness_global = :completeness WHERE fiche_id = :id',
            ['completeness' => $completeness, 'id' => Ulid::fromString($lieuId)->toBinary()],
            ['completeness' => ParameterType::INTEGER, 'id' => ParameterType::BINARY],
        );
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }
}
