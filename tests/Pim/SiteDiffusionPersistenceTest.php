<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class SiteDiffusionPersistenceTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testSelectionIsReplacedAndResetsWorkflow(): void
    {
        $obligatoire = new SiteDiffusion('BP_TEST', 'Business Profilers', 'Réseau', true, false, 0, []);
        $optionnel = new SiteDiffusion('KACTUS_TEST', 'Kactus', 'Partenaires', false, false, 1, [TypeFiche::Lieu]);
        $payant = new SiteDiffusion('FIGARO_TEST', 'Le Figaro Événements', 'Régies', false, true, 2, []);
        $this->entityManager->persist($obligatoire);
        $this->entityManager->persist($optionnel);
        $this->entityManager->persist($payant);

        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->changeLabel('Château de test');
        $this->entityManager->persist($fiche);
        $fiche->replaceSiteDiffusion([$obligatoire, $optionnel]);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $rechargee = $this->entityManager->find(Fiche::class, $fiche->id());
        self::assertInstanceOf(Fiche::class, $rechargee);
        self::assertSame([$obligatoire->id(), $optionnel->id()], $rechargee->siteDiffusionIds());

        $rechargee->publishForImport();
        $this->entityManager->flush();
        self::assertSame(StatutFiche::Publiee, $rechargee->status());

        $sites = self::getContainer()->get(SiteDiffusionRepository::class);
        $figaro = $sites->findOneByCode('FIGARO_TEST');
        self::assertInstanceOf(SiteDiffusion::class, $figaro);
        $rechargee->replaceSiteDiffusion([$figaro]);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Changer la sélection de sites est une modification métier : la fiche repart en cours.
        $finale = $this->entityManager->find(Fiche::class, $fiche->id());
        self::assertInstanceOf(Fiche::class, $finale);
        self::assertSame([$figaro->id()], $finale->siteDiffusionIds());
        self::assertSame(StatutFiche::EnCours, $finale->status());
    }

    public function testDefaultSelectionMergesMandatoryAndPerGammeSites(): void
    {
        $obligatoire = new SiteDiffusion('BP_TEST', 'Business Profilers', 'Réseau', true, false, 0, []);
        $pourLieux = new SiteDiffusion('KACTUS_TEST', 'Kactus', 'Partenaires', false, false, 1, [TypeFiche::Lieu]);
        $inactif = new SiteDiffusion('VENUU_TEST', 'Venuu', 'International', false, false, 2, [TypeFiche::Lieu]);
        $inactif->deactivate();
        $this->entityManager->persist($obligatoire);
        $this->entityManager->persist($pourLieux);
        $this->entityManager->persist($inactif);
        $this->entityManager->flush();

        $sites = self::getContainer()->get(SiteDiffusionRepository::class);
        $parDefautLieu = array_map(
            static fn (SiteDiffusion $site): string => $site->code(),
            $sites->findParDefautPourGamme(TypeFiche::Lieu),
        );
        $parDefautResto = array_map(
            static fn (SiteDiffusion $site): string => $site->code(),
            $sites->findParDefautPourGamme(TypeFiche::Restaurant),
        );

        self::assertSame(['BP_TEST', 'KACTUS_TEST'], $parDefautLieu);
        self::assertSame(['BP_TEST'], $parDefautResto);
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
    }
}
