<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Audit\Restore\AuditRestorer;
use App\Audit\Restore\NotRestorableException;
use App\Audit\Restore\StaleVersionException;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class AuditRestorerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private AuditRestorer $restorer;

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
        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
        $this->restorer = self::getContainer()->get(AuditRestorer::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testRestoringAChangeReappliesOldValueAndAuditsIt(): void
    {
        $lieu = $this->lieu('Avant', 'Paris');
        $lieu->changeLabel('Après');
        $this->entityManager->flush();
        $change = $this->changeForPath('nom');
        $version = $lieu->fiche()->version();

        $this->restorer->restoreChange($change, $version);

        self::assertSame('Avant', $lieu->fiche()->label());
        $restored = $this->connection->fetchAssociative(
            'SELECT c.old_value, c.new_value FROM audit_change c
             JOIN audit_revision r ON r.id = c.revision_id
             WHERE c.path = ? ORDER BY c.id DESC LIMIT 1',
            ['nom'],
        );
        self::assertIsArray($restored);
        self::assertSame('Après', json_decode((string) $restored['old_value']));
        self::assertSame('Avant', json_decode((string) $restored['new_value']));
    }

    public function testStaleVersionIsRejectedWithoutModifyingTheFiche(): void
    {
        $lieu = $this->lieu('Avant', 'Paris');
        $lieu->changeLabel('Après');
        $this->entityManager->flush();
        $change = $this->changeForPath('nom');

        $this->expectException(StaleVersionException::class);
        try {
            $this->restorer->restoreChange(
                $change,
                $lieu->fiche()->version() + 1,
            );
        } finally {
            self::assertSame('Après', $lieu->fiche()->label());
        }
    }

    public function testNonRestorablePathIsRejected(): void
    {
        $lieu = $this->lieu('Avant', 'Paris');
        $revision = new AuditRevision(
            $lieu->fiche()->idString(),
            'update',
            'pim',
            'test',
            [],
            'corr',
        );
        $change = new AuditChange(
            $revision,
            'workflow.status',
            'publiee',
            'en_cours',
        );

        $this->expectException(NotRestorableException::class);
        $this->restorer->restoreChange($change, $lieu->fiche()->version());
    }

    public function testRestoringARevisionAppliesAllRestorableChangesInOneFlush(): void
    {
        $lieu = $this->lieu('Avant', 'Paris');
        $lieu->changeLabel('Après');
        $lieu->localisation()?->changeVille('Lyon');
        $lieu->fiche()->markChanged();
        $this->entityManager->flush();
        $revisionId = $this->connection->fetchOne(
            'SELECT r.id FROM audit_revision r
             JOIN audit_change c ON c.revision_id = r.id
             WHERE c.path = ? ORDER BY r.id DESC LIMIT 1',
            ['nom'],
        );
        self::assertNotFalse($revisionId);
        $revision = $this->entityManager->find(
            AuditRevision::class,
            $revisionId,
        );
        self::assertInstanceOf(AuditRevision::class, $revision);
        $revisionCountBefore = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM audit_revision',
        );

        $applied = $this->restorer->restoreRevision(
            $revision,
            $lieu->fiche()->version(),
        );

        self::assertContains('nom', $applied);
        self::assertContains('localisation.ville', $applied);
        self::assertSame('Avant', $lieu->fiche()->label());
        self::assertSame('Paris', $lieu->localisation()?->ville());
        self::assertSame(
            $revisionCountBefore + 1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM audit_revision',
            ),
        );
    }

    private function lieu(string $label, string $ville): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $localisation = new Localisation();
        $localisation->changeVille($ville);
        $lieu->changeLocalisation($localisation);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $lieu;
    }

    private function changeForPath(string $path): AuditChange
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM audit_change WHERE path = ? ORDER BY id DESC LIMIT 1',
            [$path],
        );
        self::assertNotFalse($id);
        $change = $this->entityManager->find(AuditChange::class, $id);
        self::assertInstanceOf(AuditChange::class, $change);

        return $change;
    }

    private function clear(): void
    {
        foreach (
            [
                'audit_change',
                'audit_revision',
                'outbox_message',
                'pim_fiche_attribute_value',
                'pim_fiche_administratif',
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
