<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\FicheSearchIndexer;
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

        $codePage = $this->search('7');
        self::assertSame($paris->id(), $codePage->results[0]->id ?? null);
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
        $lieu->changeCode($code);
        $lieu->changeLabel($label);
        $lieu->changeDescGenerale('Description événementielle du lieu.');
        if (StatutFiche::Publiee === $status) {
            $lieu->fiche()->publishFromExternal();
        }
        $localisation = new Localisation();
        $localisation->changeRuePostale('10 avenue de France');
        $localisation->changeCodePostal('75001');
        $localisation->changeVille($ville);
        $localisation->changePays('France');
        $lieu->changeLocalisation($localisation);

        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $this->indexer->index($lieu->fiche());

        return $lieu;
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
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }
}
