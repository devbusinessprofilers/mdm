<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\FicheEnrichmentScanRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Filtre « jamais scanné » : never/mark/invalidation par modif/fraîcheur/--rescan. */
#[Group('database')]
final class FicheEnrichmentScanTest extends KernelTestCase
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
            $this->connection->executeStatement('DELETE FROM pim_fiche_enrichment_scan');
            $this->connection->executeStatement('DELETE FROM pim_fiche_administratif');
            $this->connection->executeStatement('DELETE FROM pim_lieu');
            $this->connection->executeStatement('DELETE FROM pim_restaurant');
            $this->connection->executeStatement('DELETE FROM pim_activite');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
        }
        parent::tearDown();
    }

    public function testLeFiltreNeGardeQueLesLieuxAScanner(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $lieux = self::getContainer()->get(LieuRepository::class);
        $scans = self::getContainer()->get(FicheEnrichmentScanRepository::class);

        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel scanné');
        $em->persist($lieu);
        $em->flush();
        $id = $lieu->id();
        $modifieLe = $lieu->fiche()->updatedAt();
        $seuilLarge = $modifieLe->modify('-1 day');
        $s = SuggestionSource::Sirene->value;

        // Jamais scanné → présent.
        self::assertTrue($this->contient($lieux->findBatchAfter(null, 10, $s, $seuilLarge), $id));

        // Scanné après la dernière modif → exclu.
        $scans->marquer([$id], SuggestionSource::Sirene, $modifieLe->modify('+60 seconds'));
        self::assertFalse($this->contient($lieux->findBatchAfter(null, 10, $s, $seuilLarge), $id));

        // Scan antérieur à la modif (fiche modifiée depuis) → ré-inclus.
        $scans->marquer([$id], SuggestionSource::Sirene, $modifieLe->modify('-60 seconds'));
        self::assertTrue($this->contient($lieux->findBatchAfter(null, 10, $s, $seuilLarge), $id));

        // Fiche modifiée dans la seconde du départ du run → ré-incluse (le
        // marquage stocke scanned_at une seconde avant le run).
        $scans->marquer([$id], SuggestionSource::Sirene, $modifieLe);
        self::assertTrue($this->contient($lieux->findBatchAfter(null, 10, $s, $seuilLarge), $id));

        // Scan récent mais antérieur au seuil de fraîcheur → ré-inclus.
        $scans->marquer([$id], SuggestionSource::Sirene, $modifieLe->modify('+60 seconds'));
        $seuilStrict = $modifieLe->modify('+120 seconds');
        self::assertTrue($this->contient($lieux->findBatchAfter(null, 10, $s, $seuilStrict), $id));

        // --rescan (pas de filtre de source) → toujours présent.
        self::assertTrue($this->contient($lieux->findBatchAfter(null, 10, null, null), $id));
    }

    public function testLeFiltreSAppliqueAussiAuxRestaurantsEtActivites(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $restaurants = self::getContainer()->get(RestaurantRepository::class);
        $activites = self::getContainer()->get(ActiviteRepository::class);
        $scans = self::getContainer()->get(FicheEnrichmentScanRepository::class);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Resto scanné');
        $activite = new Activite();
        $activite->changeLabel('Activité scannée');
        $em->persist($restaurant);
        $em->persist($activite);
        $em->flush();
        $seuil = $restaurant->fiche()->updatedAt()->modify('-1 day');

        // Jamais scannés → présents.
        self::assertTrue($this->contient($restaurants->findBatchAfter(null, 10, SuggestionSource::Geoapify->value, $seuil), $restaurant->id()));
        self::assertTrue($this->contient($activites->findBatchAfter(null, 10, SuggestionSource::DataTourisme->value, $seuil), $activite->id()));

        // Scannés après modif → exclus.
        $scans->marquer([$restaurant->id()], SuggestionSource::Geoapify, $restaurant->fiche()->updatedAt()->modify('+60 seconds'));
        $scans->marquer([$activite->id()], SuggestionSource::DataTourisme, $activite->fiche()->updatedAt()->modify('+60 seconds'));
        self::assertFalse($this->contient($restaurants->findBatchAfter(null, 10, SuggestionSource::Geoapify->value, $seuil), $restaurant->id()));
        self::assertFalse($this->contient($activites->findBatchAfter(null, 10, SuggestionSource::DataTourisme->value, $seuil), $activite->id()));
    }

    /**
     * @param list<Lieu|Restaurant|Activite> $lieux
     */
    private function contient(array $lieux, string $id): bool
    {
        foreach ($lieux as $lieu) {
            if ($lieu->id() === $id) {
                return true;
            }
        }

        return false;
    }
}
