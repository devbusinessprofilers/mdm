<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\UnpublishDocument;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Service\FicheWorkflowManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Archiver et supprimer font la même chose pour toutes les gammes : les
 * documents publiés quittent le stockage public, les originaux sont purgés
 * du DAM. Testé sur un Restaurant, la gamme qui passait autrefois à côté.
 */
#[Group('database')]
final class FicheWorkflowManagerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private FicheWorkflowManager $workflow;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->workflow = self::getContainer()->get(FicheWorkflowManager::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testSupprimerUnRestaurantPurgeSesMediasEtSesDocumentsPublies(): void
    {
        $restaurant = $this->restaurantAvecPhotoEtDocumentPublie();

        $this->workflow->delete($restaurant);

        self::assertSame(0, $this->countRows('pim_restaurant'));
        self::assertSame(1, $this->outboxCount(DeleteMedia::class));
        self::assertSame(1, $this->outboxCount(UnpublishDocument::class));
    }

    public function testArchiverUnRestaurantDepublieSesDocuments(): void
    {
        $restaurant = $this->restaurantAvecPhotoEtDocumentPublie();

        $this->workflow->archive($restaurant->fiche(), 'validateur');

        self::assertSame(StatutFiche::Archivee, $restaurant->fiche()->status());
        self::assertSame(1, $this->outboxCount(UnpublishDocument::class));
        self::assertSame(0, $this->outboxCount(DeleteMedia::class));
        self::assertNull($this->connection->fetchOne(
            "SELECT public_storage_key FROM pim_ressource_lieu WHERE nature = 'document'",
        ) ?: null);
    }

    private function restaurantAvecPhotoEtDocumentPublie(): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Brasserie des tests');
        $restaurant->fiche()->publishForImport();

        $photo = new RessourceLieu();
        $photo->changeDamAssetId((string) new Ulid());
        $photo->changeNature(NatureRessource::Photo);
        $photo->changeUsage('PHOTO_DIVERSE');
        $restaurant->fiche()->addResource($photo);

        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::RestaurantMenu);
        $document->confirmPublication('documents/menus.pdf');
        $restaurant->fiche()->addResource($document);

        $this->entityManager->persist($restaurant);
        $restaurant->fiche()->preserveWorkflowDuring(fn () => $this->entityManager->flush());
        $this->connection->executeStatement('DELETE FROM outbox_message');

        return $restaurant;
    }

    private function outboxCount(string $messageType): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
            [$messageType],
        );
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    private function clear(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['outbox_message', 'pim_ressource_lieu', 'pim_restaurant', 'pim_fiche_search', 'pim_fiche', 'pim_localisation'] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
