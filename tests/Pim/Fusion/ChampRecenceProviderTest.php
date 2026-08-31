<?php

declare(strict_types=1);

namespace App\Tests\Pim\Fusion;

use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Fusion\ChampRecenceProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class ChampRecenceProviderTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testLesQuatreReglesDePreselection(): void
    {
        $lieuA = new Lieu();
        $lieuA->changeLabel('Fiche A');
        $lieuB = new Lieu();
        $lieuB->changeLabel('Fiche B');
        $this->entityManager->persist($lieuA);
        $this->entityManager->persist($lieuB);
        $this->entityManager->flush();
        $ficheA = $lieuA->fiche();
        $ficheB = $lieuB->fiche();

        // Audit maîtrisé : on repart de zéro puis on pose nos révisions datées.
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->reviser($ficheA->idString(), 'nom', '2026-08-01 10:00:00');
        $this->reviser($ficheB->idString(), 'nom', '2026-08-20 10:00:00');
        $this->reviser($ficheA->idString(), 'lieu.generaleDescription', '2026-08-05 10:00:00');
        // B plus récemment modifiée dans son ensemble : repli de la règle 4.
        $this->connection->executeStatement(
            'UPDATE pim_fiche SET updated_at = ? WHERE id = ?',
            ['2026-08-25 10:00:00', $ficheB->id()->toBinary()],
        );
        $this->connection->executeStatement(
            'UPDATE pim_fiche SET updated_at = ? WHERE id = ?',
            ['2026-08-10 10:00:00', $ficheA->id()->toBinary()],
        );
        $this->entityManager->refresh($ficheA);
        $this->entityManager->refresh($ficheB);

        $provider = new ChampRecenceProvider(self::getContainer()->get(\App\Audit\Repository\AuditChangeRepository::class));

        // Règle 1 : la valeur non vide gagne quelles que soient les dates.
        self::assertSame('a', $provider->preselection($ficheA, $ficheB, 'nom', 'Fiche A', null));
        self::assertSame('b', $provider->preselection($ficheA, $ficheB, 'nom', '  ', 'Fiche B'));
        // Règle 2 : deux dates d'audit, la plus récente l'emporte.
        self::assertSame('b', $provider->preselection($ficheA, $ficheB, 'nom', 'Fiche A', 'Fiche B'));
        // Règle 3 : seule A est auditée sur ce champ, elle gagne.
        self::assertSame('a', $provider->preselection($ficheA, $ficheB, 'lieu.generaleDescription', 'texte A', 'texte B'));
        // Règle 4 : aucun audit, la fiche modifiée en dernier (B) gagne.
        self::assertSame('b', $provider->preselection($ficheA, $ficheB, 'fiche.telephone', '01', '02'));
        // Dates exposées pour l'affichage de l'écran.
        self::assertNotNull($provider->derniereModification($ficheA, 'nom'));
        self::assertNull($provider->derniereModification($ficheB, 'lieu.generaleDescription'));
    }

    private function reviser(string $ficheId, string $path, string $date): void
    {
        $revision = new AuditRevision($ficheId, 'update', 'pim', 'test@example.test', [], 'test');
        new AuditChange($revision, $path, null, 'valeur');
        $this->entityManager->persist($revision);
        $this->entityManager->flush();
        $this->connection->executeStatement(
            'UPDATE audit_revision SET created_at = ? WHERE id = ?',
            [$date, \Symfony\Component\Uid\Ulid::fromString($revision->id())->toBinary()],
        );
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
    }
}
