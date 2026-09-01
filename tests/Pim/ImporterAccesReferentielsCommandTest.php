<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Command\ImporterAeroportsCommand;
use App\Pim\Command\ImporterGrandesVillesCommand;
use App\Pim\Repository\AeroportReferenceRepository;
use App\Pim\Repository\GrandeVilleReferenceRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Imports des référentiels statiques du bloc Accès depuis un fichier local :
 * filtres OurAirports (large/medium à trafic régulier) et GeoNames
 * (population plancher), remplacement en bloc.
 */
#[Group('database')]
final class ImporterAccesReferentielsCommandTest extends KernelTestCase
{
    private Connection $connection;
    /** @var list<string> */
    private array $fichiers = [];

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM pim_aeroport_reference');
        $this->connection->executeStatement('DELETE FROM pim_grande_ville_reference');
    }

    protected function tearDown(): void
    {
        foreach ($this->fichiers as $fichier) {
            @unlink($fichier);
        }
        parent::tearDown();
    }

    public function testSeulsLesAeroportsCommerciauxReguliersSontImportes(): void
    {
        $csv = <<<'CSV'
            id,ident,type,name,latitude_deg,longitude_deg,elevation_ft,continent,iso_country,iso_region,municipality,scheduled_service,gps_code,iata_code,local_code,home_link,wikipedia_link,keywords
            1,LFPG,large_airport,Charles de Gaulle International Airport,49.0097,2.5479,392,EU,FR,FR-IDF,Paris,yes,LFPG,CDG,,,
            2,XHEL,heliport,Héliport filtré,48.8,2.2,100,EU,FR,FR-IDF,Paris,yes,,,,,
            3,LFXX,medium_airport,Aérodrome sans lignes,47.0,3.0,300,EU,FR,FR-BFC,Nulle-Part,no,,,,,
            CSV;
        $tester = new CommandTester(new ImporterAeroportsCommand(self::getContainer()->get(AeroportReferenceRepository::class), new MockHttpClient()));

        $tester->execute(['fichier' => $this->fichier($csv, '.csv')]);

        $tester->assertCommandIsSuccessful();
        $lignes = $this->connection->fetchAllAssociative('SELECT nom, code_iata, code_pays FROM pim_aeroport_reference');
        self::assertSame([['nom' => 'Charles de Gaulle International Airport', 'code_iata' => 'CDG', 'code_pays' => 'FR']], $lignes);
    }

    public function testLesVillesSousLePlancherDePopulationSontFiltrees(): void
    {
        $tsv = "2988507\tParis\tParis\t\t48.85341\t2.3488\tP\tPPLC\tFR\t\t11\t75\t751\t75056\t2138551\t\t42\tEurope/Paris\t2023-01-01\n"
            ."0000000\tHameau\tHameau\t\t48.0\t2.0\tP\tPPL\tFR\t\t11\t75\t751\t75001\t900\t\t42\tEurope/Paris\t2023-01-01\n";
        $tester = new CommandTester(new ImporterGrandesVillesCommand(self::getContainer()->get(GrandeVilleReferenceRepository::class), new MockHttpClient()));

        $tester->execute(['fichier' => $this->fichier($tsv, '.txt')]);

        $tester->assertCommandIsSuccessful();
        $lignes = $this->connection->fetchAllAssociative('SELECT nom, code_pays, population FROM pim_grande_ville_reference');
        self::assertSame([['nom' => 'Paris', 'code_pays' => 'FR', 'population' => 2138551]], $lignes);
    }

    private function fichier(string $contenu, string $suffixe): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'acces-test');
        self::assertIsString($chemin);
        rename($chemin, $chemin.$suffixe);
        $chemin .= $suffixe;
        file_put_contents($chemin, $contenu);
        $this->fichiers[] = $chemin;

        return $chemin;
    }
}
