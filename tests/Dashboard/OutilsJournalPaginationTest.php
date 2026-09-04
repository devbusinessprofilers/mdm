<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Pim\Entity\FicheEnrichmentRun;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Pagination du journal /outils : 50 lignes par page sur les 1000 traitements les plus récents. */
#[Group('database')]
final class OutilsJournalPaginationTest extends WebTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            foreach (['pim_fiche_enrichment_run', 'pim_fiche_search', 'pim_fiche_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'account_user'] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }
        parent::tearDown();
    }

    public function testLeJournalPagineParCinquanteEtBorneLaPage(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('outils-pagination@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('x');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château très enrichi');
        $entityManager->persist($lieu);
        for ($i = 0; $i < 60; ++$i) {
            $entityManager->persist(new FicheEnrichmentRun($lieu->fiche()));
        }
        $entityManager->flush();
        $client->loginUser($user);

        // Page 1 : 50 lignes (+ l'en-tête role=row) et le pied annonce 2 pages.
        $crawler = $client->request('GET', '/outils');
        self::assertResponseIsSuccessful();
        self::assertSame(51, $crawler->filter('[role="row"]')->count());
        self::assertSelectorTextContains('body', 'Page 1 / 2');
        self::assertSelectorTextContains('body', '60 traitements · 0 en échec');

        // Page 2 : le reste.
        $crawler = $client->request('GET', '/outils', ['page' => 2]);
        self::assertSame(11, $crawler->filter('[role="row"]')->count());
        self::assertSelectorTextContains('body', 'Page 2 / 2');

        // Page hors bornes : ramenée à la dernière page, sans erreur.
        $crawler = $client->request('GET', '/outils', ['page' => 99]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Page 2 / 2');

        // Le filtre par famille garde la pagination cohérente.
        $client->request('GET', '/outils', ['famille' => 'enrichissement', 'page' => 2]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Page 2 / 2');
    }
}
