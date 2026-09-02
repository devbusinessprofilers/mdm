<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\SalesforceCsvBuilder;
use App\Etl\Service\SalesforceProduitsCsvExporter;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Découpage de l'export Produits en paquets bornés par la taille de la pièce
 * jointe : une modification de masse part en une poignée d'e-mails, chacun
 * sous le plafond, avec ses propres en-têtes. Fiches Activité minimales : le
 * code produit exige la persistance, mais l'exporteur ne consulte ses
 * repositories que pour les Lieux et les sites cochés, absents ici.
 */
#[Group('database')]
final class SalesforceCsvPaquetsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        $this->clearTables();
        parent::tearDown();
    }

    public function testUnPetitLotTientDansUnSeulPaquet(): void
    {
        $fiches = [self::fiche('Alpha'), self::fiche('Bravo'), self::fiche('Charlie')];

        $paquets = iterator_to_array($this->exporter()->csvParPaquets($fiches), false);

        self::assertCount(1, $paquets);
        self::assertCount(3, $paquets[0]['ficheIds']);
        self::assertSame(
            array_map(static fn (Fiche $fiche): string => $fiche->idString(), $fiches),
            $paquets[0]['ficheIds'],
        );
        // En-têtes + une ligne par fiche.
        self::assertSame(4, substr_count($paquets[0]['csv'], "\r\n"));
        self::assertStringStartsWith('"ID_PRODUCT"', $paquets[0]['csv']);
    }

    public function testLePlafondDeTailleOuvreUnNouveauPaquetAvecSesEntetes(): void
    {
        $exporter = $this->exporter();
        $fiches = [self::fiche('Alpha'), self::fiche('Bravo'), self::fiche('Charlie')];
        // Plafond calibré pour exactement deux lignes après les en-têtes.
        $entete = strlen(SalesforceCsvBuilder::ligne(SalesforceProduitsCsvExporter::ENTETES));
        [$seule] = iterator_to_array($exporter->csvParPaquets([$fiches[0]]), false);
        $ligne = strlen($seule['csv']) - $entete;
        $plafond = $entete + (2 * $ligne) + 1;

        $paquets = iterator_to_array($exporter->csvParPaquets($fiches, $plafond), false);

        self::assertCount(2, $paquets);
        self::assertCount(2, $paquets[0]['ficheIds']);
        self::assertCount(1, $paquets[1]['ficheIds']);
        self::assertSame($fiches[2]->idString(), $paquets[1]['ficheIds'][0]);
        foreach ($paquets as $paquet) {
            self::assertStringStartsWith('"ID_PRODUCT"', $paquet['csv']);
            self::assertLessThanOrEqual($plafond, strlen($paquet['csv']));
        }
    }

    public function testUneLignePlusGrosseQueLePlafondPartSeule(): void
    {
        $fiches = [self::fiche('Alpha'), self::fiche('Bravo')];

        // Plafond plus petit que les en-têtes : chaque fiche part quand même,
        // seule dans son paquet — jamais de fiche silencieusement écartée.
        $paquets = iterator_to_array($this->exporter()->csvParPaquets($fiches, 10), false);

        self::assertCount(2, $paquets);
        self::assertCount(1, $paquets[0]['ficheIds']);
        self::assertCount(1, $paquets[1]['ficheIds']);
    }

    public function testUnLotVideNeProduitAucunPaquet(): void
    {
        self::assertSame([], iterator_to_array($this->exporter()->csvParPaquets([]), false));
    }

    private function exporter(): SalesforceProduitsCsvExporter
    {
        return self::getContainer()->get(SalesforceProduitsCsvExporter::class);
    }

    /** Persistée : le code produit (ID_PRODUCT) est attribué à l'enregistrement. */
    private static function fiche(string $label): Fiche
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $activite = new Activite();
        $activite->changeLabel($label);
        $entityManager->persist($activite);
        $entityManager->flush();

        return $activite->fiche();
    }

    private function clearTables(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        foreach (['outbox_message', 'pim_fiche_search', 'pim_activite', 'pim_fiche', 'pim_localisation'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
