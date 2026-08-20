<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class ImportLegacyCollaborateursCommandTest extends KernelTestCase
{
    private const TABLES = ['pim_fiche_affiliation', 'pim_fiche_collaborateur', 'pim_fiche', 'account_user', 'outbox_message'];
    private const ADMIN_EMAIL = 'import-admin@example.com';

    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private ?string $xlsxFile = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->cleanTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanTables();
        }
        if (null !== $this->xlsxFile) {
            @unlink($this->xlsxFile);
        }

        parent::tearDown();
    }

    public function testPremierContactValideRecoitTousLesDroits(): void
    {
        $this->fixtures([9101, 9102]);
        $tester = $this->tester();

        // Fiche 9101 : deux entrées valides. Fiche 9102 : la première entrée
        // est invalide, les droits doivent aller à la première VALIDE.
        $file = $this->writeSampleXlsx([
            ['9101', "premier@example.com\nsecond@example.com", "Un\nDeux", "Anna\nBob", '', ''],
            ['9102', "pas-un-email\nvalide@example.com", "Ko\nOk", "Cé\nDé", '', ''],
        ]);
        $tester->execute(['--file' => $file, '--created-by' => self::ADMIN_EMAIL]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertSame([1, 1, 1, 0], $this->droits('premier@example.com'));
        self::assertSame([0, 0, 0, 0], $this->droits('second@example.com'));
        self::assertSame([1, 1, 1, 0], $this->droits('valide@example.com'));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_affiliation'));

        // Re-run : idempotent, aucune affiliation ni aucun droit supplémentaire.
        $tester->execute(['--file' => $file, '--created-by' => self::ADMIN_EMAIL]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_affiliation'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_affiliation WHERE receives_requests = 1'));
        self::assertSame([0, 0, 0, 0], $this->droits('second@example.com'));
    }

    public function testFicheDejaAffilieeNeRecoitAucunDroit(): void
    {
        [$fiche] = $this->fixtures([9103]);
        $existant = new FicheCollaborateur('existant@example.com', 'Déjà', 'Là');
        $admin = $this->admin();
        $this->entityManager->persist($existant);
        $this->entityManager->persist(new FicheAffiliation($existant, $fiche, FicheAffiliationRole::Utilisateur, $admin));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $tester = $this->tester();
        $file = $this->writeSampleXlsx([
            ['9103', 'nouveau@example.com', 'Nouveau', 'Venu', '', ''],
        ]);
        $tester->execute(['--file' => $file, '--created-by' => self::ADMIN_EMAIL]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertSame([0, 0, 0, 0], $this->droits('existant@example.com'));
        self::assertSame([0, 0, 0, 0], $this->droits('nouveau@example.com'));
    }

    public function testDryRunCompteSansEcrire(): void
    {
        $this->fixtures([9104]);
        $tester = $this->tester();
        $file = $this->writeSampleXlsx([
            ['9104', "premier@example.com\nsecond@example.com", "Un\nDeux", "Anna\nBob", '', ''],
        ]);
        $tester->execute(['--file' => $file, '--created-by' => self::ADMIN_EMAIL, '--dry-run' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertStringContainsString('premiers contacts (tous droits)', $tester->getDisplay());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_affiliation'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_collaborateur'));
    }

    /**
     * @param list<int> $codes
     *
     * @return list<Fiche>
     */
    private function fixtures(array $codes): array
    {
        $admin = new User(self::ADMIN_EMAIL, ['ROLE_SUPER_ADMIN']);
        $admin->setPassword('test-password-hash');
        $this->entityManager->persist($admin);
        $fiches = [];
        foreach ($codes as $code) {
            $fiche = new Fiche(TypeFiche::Lieu);
            $fiche->assignImportedCode($code);
            $fiche->changeLabel('Fiche '.$code);
            $this->entityManager->persist($fiche);
            $fiches[] = $fiche;
        }
        $this->entityManager->flush();

        return $fiches;
    }

    private function admin(): User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::ADMIN_EMAIL])
            ?? throw new \LogicException('Fixture admin absente.');
    }

    /** @return list<int> receives_requests, traite_contenus, traite_paiements, repli */
    private function droits(string $email): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT a.receives_requests, a.traite_contenus, a.traite_paiements, a.repli
             FROM pim_fiche_affiliation a
             JOIN pim_fiche_collaborateur c ON c.id = a.collaborateur_id
             WHERE c.email = ?',
            [$email],
        );
        self::assertIsArray($row, sprintf('Affiliation absente pour %s.', $email));

        return array_map(intval(...), array_values($row));
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:legacy:import-collaborateurs'));
    }

    /** @param list<list<string>> $rows */
    private function writeSampleXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mdm-legacy-collab-');
        self::assertIsString($path);
        $this->xlsxFile = $path;
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['ID Syspad', 'Identifiant email', 'Nom', 'Prénom', 'Adhérent Business Premium', 'Attribution visibilité']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    private function cleanTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
