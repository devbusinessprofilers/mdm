<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\SiteDiffusion;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class DoctrineAuditSubscriberTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (
            !str_starts_with(
                (string) getenv('TEST_MESSENGER_PIM_DSN'),
                'doctrine://',
            )
        ) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testOneFlushProducesOneRevisionWithOnlyChangedBusinessPaths(): void
    {
        $entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
        $lieu = new Lieu();
        $lieu->changeLabel('Avant');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $entityManager->persist($lieu);
        $entityManager->flush();
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $lieu->changeLabel('Après');
        $localisation->changeVille('Lyon');
        $lieu->fiche()->markChanged();
        $entityManager->flush();
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM audit_revision',
            ),
        );
        $paths = $this->connection->fetchFirstColumn(
            'SELECT path FROM audit_change ORDER BY path',
        );
        self::assertContains('nom', $paths);
        self::assertContains('localisation.ville', $paths);
        self::assertNotContains('fiche.updatedAt', $paths);
        self::assertNotContains('updatedAt', $paths);
    }

    public function testServiceChangesUseStableServiceBusinessPaths(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Avant');
        $service->changeTarifParHeure(80.0);
        $entityManager->persist($service);
        $entityManager->flush();

        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');

        $service->changeTarifParHeure(95.5);
        $service->fiche()->markChanged();
        $entityManager->flush();

        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM audit_revision',
            ),
        );
        self::assertContains(
            'service.tarifParHeure',
            $this->connection->fetchFirstColumn(
                'SELECT path FROM audit_change ORDER BY path',
            ),
        );
    }

    public function testPureSiteDiffusionCollectionChangeProducesARevision(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Château');
        $site = new SiteDiffusion('site-audit', 'Site audit', 'Groupe test');
        $entityManager->persist($site);
        $entityManager->persist($lieu);
        $entityManager->flush();

        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');

        // Ajout d'un canal de diffusion sans transition de workflow : seule la
        // collection change, le touch technique (updatedAt/version) est ignoré.
        $lieu->fiche()->ajouterSitesDiffusion([$site]);
        $entityManager->flush();

        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM audit_revision',
            ),
        );
        $rows = $this->connection->fetchAllAssociative(
            'SELECT path, old_value, new_value FROM audit_change',
        );
        self::assertCount(1, $rows);
        self::assertSame('sitesDiffusion', $rows[0]['path']);
        self::assertSame(
            [],
            json_decode((string) $rows[0]['old_value'], true),
        );
        self::assertSame(
            [$site->id()],
            json_decode((string) $rows[0]['new_value'], true),
        );
    }

    public function testPureTouchWithoutCollectionChangeProducesNoRevision(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Château');
        $entityManager->persist($lieu);
        $entityManager->flush();

        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');

        // Mise à jour purement technique : aucun bruit d'audit attendu.
        $lieu->fiche()->markSystemChanged();
        $entityManager->flush();

        self::assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM audit_revision',
            ),
        );
    }

    private function clear(): void
    {
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement(
            'DELETE FROM pim_fiche_site_diffusion',
        );
        $this->connection->executeStatement('DELETE FROM pim_site_diffusion');
        $this->connection->executeStatement(
            'DELETE FROM pim_fiche_attribute_value',
        );
        $this->connection->executeStatement(
            'DELETE FROM pim_lieu_administratif',
        );
        $this->connection->executeStatement(
            'DELETE FROM pim_lieu_tarification',
        );
        $this->connection->executeStatement(
            'DELETE FROM pim_service_evenementiel',
        );
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }
}
