<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Command\ImporterClassementsAtoutFranceCommand;
use App\Pim\Repository\ClassementAtoutFranceRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Import du référentiel Atout France depuis un fichier local : en-tête réel
 * (BOM, séparateur « ; »), lignes sans classement lisible écartées,
 * remplacement en bloc.
 */
#[Group('database')]
final class ImporterClassementsAtoutFranceCommandTest extends KernelTestCase
{
    private Connection $connection;
    private ?string $fichier = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM pim_classement_atout_france');
    }

    protected function tearDown(): void
    {
        if (null !== $this->fichier) {
            @unlink($this->fichier);
        }
        parent::tearDown();
    }

    public function testSeulesLesLignesClasseesExploitablesSontImportees(): void
    {
        $csv = "\u{FEFF}DATE DE CLASSEMENT;TYPOLOGIE ÉTABLISSEMENT;CLASSEMENT;CATÉGORIE;MENTION (villages de vacances);NOM COMMERCIAL;ADRESSE;CODE POSTAL;COMMUNE;SITE INTERNET;TYPE DE SÉJOUR;CAPACITÉ D'ACCUEIL (PERSONNES);NOMBRE DE CHAMBRES;NOMBRE D'EMPLACEMENTS;NOMBRE D'UNITES D'HABITATION (résidences de tourisme);NOMBRE DE LOGEMENTS (villages de vacances);classement prorogé\n"
            ."19/06/2023;HÔTEL DE TOURISME;3 étoiles;-;-;1924 HÔTEL;2 Rue Gabriel Péri;38000;GRENOBLE;https://www.1924hotel.com/;-;62;37;-;-;-;non\n"
            ."01/02/2024;CAMPING;4 étoiles;-;-;CAMPING DES FLOTS;Route de la plage;17000;LA ROCHELLE;;-;300;-;120;-;-;non\n"
            ."15/03/2024;HÔTEL DE TOURISME;En attente;-;-;HOTEL SANS CLASSEMENT;1 rue du Test;75001;PARIS;;-;20;12;-;-;-;non\n";
        $this->fichier = (string) tempnam(sys_get_temp_dir(), 'atout-france-test');
        file_put_contents($this->fichier, $csv);
        $tester = new CommandTester(new ImporterClassementsAtoutFranceCommand(
            self::getContainer()->get(ClassementAtoutFranceRepository::class),
            new MockHttpClient(),
        ));

        $tester->execute(['fichier' => $this->fichier]);

        $tester->assertCommandIsSuccessful();
        $lignes = $this->connection->fetchAllAssociative(
            'SELECT nom, code_postal, type_etablissement, etoiles, nombre_chambres, date_classement FROM pim_classement_atout_france ORDER BY nom',
        );
        self::assertSame([
            ['nom' => '1924 HÔTEL', 'code_postal' => '38000', 'type_etablissement' => 'HÔTEL DE TOURISME', 'etoiles' => 3, 'nombre_chambres' => 37, 'date_classement' => '2023-06-19'],
            ['nom' => 'CAMPING DES FLOTS', 'code_postal' => '17000', 'type_etablissement' => 'CAMPING', 'etoiles' => 4, 'nombre_chambres' => null, 'date_classement' => '2024-02-01'],
        ], array_map(static fn (array $ligne): array => [
            'nom' => $ligne['nom'],
            'code_postal' => $ligne['code_postal'],
            'type_etablissement' => $ligne['type_etablissement'],
            'etoiles' => (int) $ligne['etoiles'],
            'nombre_chambres' => null === $ligne['nombre_chambres'] ? null : (int) $ligne['nombre_chambres'],
            'date_classement' => $ligne['date_classement'],
        ], $lignes));
    }
}
