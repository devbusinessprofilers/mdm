<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\FicheSearchIndexer;
use App\Pim\Service\GlobalSearchProvider;
use App\Pim\Service\MariaDbSearchEngine;
use App\Shared\Search\SearchQuery;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class MariaDbSearchEngineTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private FicheSearchIndexer $indexer;
    private MariaDbSearchEngine $searchEngine;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->indexer = self::getContainer()->get(FicheSearchIndexer::class);
        $this->searchEngine = self::getContainer()->get(MariaDbSearchEngine::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testSearchUsesRequiredPrefixesStatusAndExactShortCode(): void
    {
        $paris = $this->createLieu(7, 'Palais des Lumières', 'Paris', StatutFiche::Publiee);
        $lyon = $this->createLieu(8, 'Palais des Lumières', 'Lyon', StatutFiche::EnCours);
        $this->createLieu(9, 'Hôtel du Parc', 'Paris', StatutFiche::Publiee);

        $prefixPage = $this->search('pal pari');
        self::assertSame([$paris->id()], $this->ids($prefixPage->results));
        self::assertSame(1, $prefixPage->totalCount);

        $punctuationPage = $this->search('palais +pari');
        self::assertSame([$paris->id()], $this->ids($punctuationPage->results));

        $statusPage = $this->search('palais', ['status' => StatutFiche::EnCours->value]);
        self::assertSame([$lyon->id()], $this->ids($statusPage->results));
        self::assertSame(1, $statusPage->totalCount);

        $codePage = $this->search((string) $paris->code());
        self::assertSame($paris->id(), $codePage->results[0]->id ?? null);
    }

    public function testSearchFiltersByCountryAndCompleteness(): void
    {
        $paris = $this->createLieu(501, 'Palais Riviera', 'Paris', StatutFiche::Publiee);
        $rome = $this->createLieu(502, 'Palazzo Riviera', 'Rome', StatutFiche::Publiee);
        $paris->fiche()->localisation()?->changeCountryCode('FR');
        $rome->fiche()->localisation()?->changeCountryCode('IT');
        $this->entityManager->flush();
        $this->setCompleteness($paris->id(), 80);
        $this->setCompleteness($rome->id(), 30);

        $frPage = $this->search('riviera', ['country' => 'FR']);
        self::assertSame([$paris->id()], $this->ids($frPage->results));
        self::assertSame(1, $frPage->totalCount);

        $completePage = $this->search('riviera', ['completeness_min' => 50]);
        self::assertSame([$paris->id()], $this->ids($completePage->results));
        self::assertSame(1, $completePage->totalCount);

        $incompletePage = $this->search('riviera', ['completeness_max' => 40]);
        self::assertSame([$rome->id()], $this->ids($incompletePage->results));

        $allPage = $this->search('riviera', ['completeness_min' => 0, 'completeness_max' => 100]);
        self::assertSame(2, $allPage->totalCount);

        $combinedPage = $this->search('riviera', [
            'country' => 'IT',
            'completeness_min' => 10,
            'completeness_max' => 40,
            'status' => StatutFiche::Publiee->value,
        ]);
        self::assertSame([$rome->id()], $this->ids($combinedPage->results));

        $emptyPage = $this->search('riviera', ['country' => 'FR', 'completeness_max' => 40]);
        self::assertSame([], $emptyPage->results);
        self::assertSame(0, $emptyPage->totalCount);
    }

    public function testProviderRejectsInvalidCountryAndCompleteness(): void
    {
        $provider = self::getContainer()->get(GlobalSearchProvider::class);

        foreach ([
            static fn () => $provider->search('palais', country: 'France'),
            static fn () => $provider->search('palais', completenessMin: -1),
            static fn () => $provider->search('palais', completenessMax: 101),
            static fn () => $provider->search('palais', completenessMin: 60, completenessMax: 40),
        ] as $call) {
            try {
                $call();
                self::fail('Expected an InvalidArgumentException.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testScoreCursorDoesNotDuplicateOrOmitResults(): void
    {
        $expectedIds = [];
        for ($code = 100; $code < 105; ++$code) {
            $expectedIds[] = $this->createLieu($code, 'Centre de conférence', 'Nantes', StatutFiche::Publiee)->id();
        }

        $actualIds = [];
        $cursor = null;
        do {
            $page = $this->searchEngine->search(new SearchQuery(
                'conf',
                ['type' => TypeFiche::Lieu->value],
                2,
                $cursor,
            ));
            self::assertSame(5, $page->totalCount);
            $actualIds = [...$actualIds, ...$this->ids($page->results)];
            $cursor = $page->nextCursor;
        } while (null !== $cursor);

        self::assertCount(count($expectedIds), $actualIds);
        self::assertCount(count($actualIds), array_unique($actualIds));
        self::assertEqualsCanonicalizing($expectedIds, $actualIds);
    }

    public function testIndexerReplacesOutdatedContent(): void
    {
        $lieu = $this->createLieu(99, 'Ancien Belvédère', 'Bordeaux', StatutFiche::Publiee);
        self::assertSame([$lieu->id()], $this->ids($this->search('ancien')->results));

        $lieu->changeLabel('Nouveau Belvédère');
        $this->entityManager->flush();
        $this->indexer->index($lieu->fiche());

        self::assertSame([], $this->search('ancien')->results);
        self::assertSame([$lieu->id()], $this->ids($this->search('nouveau')->results));
        self::assertSame([$lieu->id()], $this->ids($this->search('belvé')->results));
    }

    public function testSearchResultsAreHydratedInOneQueryAndKeepScoreOrder(): void
    {
        $this->createLieu(301, 'Palais Alpha', 'Paris', StatutFiche::Publiee);
        $this->createLieu(302, 'Palais Beta', 'Lyon', StatutFiche::Publiee);
        $results = $this->search('palais')->results;
        $resultIds = $this->ids($results);

        $debugDataHolder = self::getContainer()->get('doctrine.debug_data_holder');
        $debugDataHolder->reset();
        $items = self::getContainer()->get(LieuRepository::class)->findListItemsByIds($resultIds);
        $queries = array_merge(...array_values($debugDataHolder->getData()));
        $selects = array_filter(
            $queries,
            static fn (array $query): bool => str_starts_with(ltrim((string) $query['sql']), 'SELECT'),
        );

        self::assertCount(1, $selects);
        self::assertSame($resultIds, array_map(static fn ($item): string => $item->id, $items));
    }

    public function testInvalidCursorIsRejected(): void
    {
        $this->createLieu(42, 'Palais des Congrès', 'Paris', StatutFiche::Publiee);

        $this->expectException(\InvalidArgumentException::class);
        $this->searchEngine->search(new SearchQuery(
            'palais',
            ['type' => TypeFiche::Lieu->value],
            cursor: 'invalid-cursor',
        ));
    }

    public function testGlobalSearchHydratesAllSupportedTypesFiltersAndPaginates(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Horizon lieu');
        $lieu->changeLocalisation($this->localisation('Paris'));

        $activite = new Activite();
        $activite->changeLabel('Horizon activité');

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Horizon restaurant');
        $restaurant->changeLocalisation($this->localisation('Lyon'));
        $restaurant->fiche()->publishForImport();
        $restaurant->fiche()->archive('test');

        $service = new ServiceEvenementiel();
        $service->changeLabel('Horizon service');

        foreach ([$lieu, $activite, $restaurant, $service] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        foreach ([$lieu, $activite, $restaurant, $service] as $entity) {
            $this->entityManager->persist(new \App\Pim\Entity\FicheSearchDocument(
                $entity->fiche(),
                implode(' ', [$entity->code(), $entity->label(), 'horizon']),
                $entity->fiche()->version(),
            ));
        }
        $this->entityManager->flush();

        $provider = self::getContainer()->get(GlobalSearchProvider::class);
        self::assertInstanceOf(GlobalSearchProvider::class, $provider);
        $rawPage = $this->searchEngine->search(new SearchQuery('horizon', [
            'type' => [
                TypeFiche::Lieu->value,
                TypeFiche::Activite->value,
                TypeFiche::Restaurant->value,
                TypeFiche::ServiceEvenementiel->value,
            ],
        ]));
        $globalPage = $provider->search('horizon');

        self::assertSame(4, $globalPage->totalCount);
        self::assertSame(
            $this->ids($rawPage->results),
            array_map(static fn ($result): string => $result->item->id, $globalPage->results),
        );
        self::assertEqualsCanonicalizing(
            [TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel],
            array_map(static fn ($result): TypeFiche => $result->item->type, $globalPage->results),
        );
        foreach ($globalPage->results as $result) {
            self::assertStringContainsString($result->item->id, $result->showUrl);
            // « voir » et « modifier » mènent tous deux à l'éditeur MDM.
            self::assertSame($result->showUrl, $result->editUrl);
        }

        $codePage = $provider->search((string) $lieu->code());
        self::assertSame($lieu->id(), $codePage->results[0]->item->id ?? null);

        $archivePage = $provider->search('horizon', TypeFiche::Restaurant, StatutFiche::Archivee);
        self::assertSame([$restaurant->id()], array_map(static fn ($result): string => $result->item->id, $archivePage->results));

        $pagedIds = [];
        $cursor = null;
        do {
            $page = $provider->search('horizon', limit: 2, cursor: $cursor);
            $pagedIds = [...$pagedIds, ...array_map(static fn ($result): string => $result->item->id, $page->results)];
            $cursor = $page->nextCursor;
        } while (null !== $cursor);
        self::assertCount(4, $pagedIds);
        self::assertCount(4, array_unique($pagedIds));

        $debugDataHolder = self::getContainer()->get('doctrine.debug_data_holder');
        $debugDataHolder->reset();
        $items = self::getContainer()->get(\App\Pim\Repository\FicheRepository::class)->findGlobalSearchItemsByIds($pagedIds);
        $queries = array_merge(...array_values($debugDataHolder->getData()));
        $selects = array_filter($queries, static fn (array $query): bool => str_starts_with(ltrim((string) $query['sql']), 'SELECT'));
        self::assertCount(1, $selects);
        self::assertSame($pagedIds, array_map(static fn ($item): string => $item->id, $items));
    }

    /** @param array<string, scalar|null> $filters */
    private function search(string $text, array $filters = []): \App\Shared\Search\SearchPage
    {
        return $this->searchEngine->search(new SearchQuery(
            $text,
            ['type' => TypeFiche::Lieu->value] + $filters,
        ));
    }

    private function createLieu(int $code, string $label, string $ville, StatutFiche $status): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $lieu->changeDescGenerale('Description événementielle du lieu.');
        $localisation = new Localisation();
        $localisation->changeRuePostale('10 avenue de France');
        $localisation->changeCodePostal('75001');
        $localisation->changeVille($ville);
        $localisation->changePays('France');
        $lieu->changeLocalisation($localisation);
        if (StatutFiche::Publiee === $status) {
            $lieu->fiche()->publishForImport();
        }

        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $this->indexer->index($lieu->fiche());

        return $lieu;
    }

    private function setCompleteness(string $lieuId, int $completeness): void
    {
        $this->connection->executeStatement(
            'UPDATE pim_lieu SET completeness_global = :completeness WHERE fiche_id = :id',
            ['completeness' => $completeness, 'id' => \Symfony\Component\Uid\Ulid::fromString($lieuId)->toBinary()],
            ['completeness' => \Doctrine\DBAL\ParameterType::INTEGER, 'id' => \Doctrine\DBAL\ParameterType::BINARY],
        );
    }

    private function localisation(string $ville): Localisation
    {
        $localisation = new Localisation();
        $localisation->changeVille($ville);
        $localisation->changePays('France');

        return $localisation;
    }

    /** @param list<\App\Shared\Search\SearchResult> $results
     *  @return list<string>
     */
    private function ids(array $results): array
    {
        return array_map(static fn ($result): string => $result->id, $results);
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }
}
