<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\MarketplaceClientInterface;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class PhotosConformiteCommandTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        self::getContainer()->set(MarketplaceClientInterface::class, new RecordingMarketplaceClient());
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $this->publishedLieu('Lieu incomplet', photos: 2);
        $this->publishedActivite('Activité conforme', photos: 2);

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Dry-run', $tester->getDisplay());
        // Rien n'a bougé en base.
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche WHERE status = 'publiee'"));
    }

    public function testApplyDemotesNonCompliant(): void
    {
        // La principale est la première photo de l'ordre : seul le minimum de
        // photos compte, aucune passe de « pose de principale » n'existe plus.
        $conforme = $this->publishedLieu('Lieu conforme', photos: 4);
        $incomplet = $this->publishedLieu('Lieu incomplet', photos: 2);
        $activite = $this->publishedActivite('Activité conforme', photos: 2);
        $restaurant = $this->publishedRestaurant('Restaurant conforme', photos: 1);
        $sansPhoto = $this->publishedActivite('Activité sans photo', photos: 0);

        $tester = $this->tester();
        $tester->execute(['--appliquer' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        // Le lieu sous le minimum est rétrogradé, les conformes restent publiés.
        self::assertSame('en_cours', $this->statutEnBase($incomplet));
        self::assertSame('publiee', $this->statutEnBase($conforme));
        self::assertSame('publiee', $this->statutEnBase($activite));
        self::assertSame('publiee', $this->statutEnBase($restaurant));
        // Le rattrapage n'applique pas l'exemption « imagerie legacy » : une
        // fiche publiée sans aucune photo PIM est rétrogradée.
        self::assertSame('en_cours', $this->statutEnBase($sansPhoto));
    }

    private function statutEnBase(Fiche $fiche): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM pim_fiche WHERE id = ?', [$fiche->id()->toBinary()]);
    }

    private function publishedLieu(string $label, int $photos): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $fiche = $lieu->fiche();
        $this->addPhotos($fiche, $photos);
        $fiche->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $fiche;
    }

    private function publishedActivite(string $label, int $photos): Fiche
    {
        $activite = new Activite();
        $activite->changeLabel($label);
        $fiche = $activite->fiche();
        $this->addPhotos($fiche, $photos);
        $fiche->publishForImport();
        $this->entityManager->persist($activite);
        $this->entityManager->flush();

        return $fiche;
    }

    private function publishedRestaurant(string $label, int $photos): Fiche
    {
        $restaurant = new Restaurant();
        $restaurant->changeLabel($label);
        $fiche = $restaurant->fiche();
        $this->addPhotos($fiche, $photos);
        $fiche->publishForImport();
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();

        return $fiche;
    }

    private function addPhotos(Fiche $fiche, int $photos): void
    {
        for ($i = 0; $i < $photos; ++$i) {
            $resource = new RessourceLieu();
            $resource->changeDamAssetId((string) new Ulid());
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeUsage('PHOTO_DIVERSE');
            $resource->changePosition($i);
            $fiche->addResource($resource);
        }
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:fiches:conformite-photos'));
    }

    private function clear(): void
    {
        foreach (
            [
                'outbox_message',
                'etl_fiche_marketplace',
                'pim_ressource_lieu',
                'pim_lieu_administratif',
                'pim_lieu_tarification',
                'pim_lieu',
                'pim_activite',
                'pim_restaurant',
                'pim_fiche',
                'pim_localisation',
            ] as $table
        ) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
