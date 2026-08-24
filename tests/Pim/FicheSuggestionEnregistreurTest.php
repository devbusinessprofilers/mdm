<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\FicheSuggestionRepository;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Pim\Service\SuggestionProposee;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Idempotence de l'enregistrement des suggestions : un re-run ne doit ni doublonner ni ré-proposer une décision prise. */
#[Group('database')]
final class FicheSuggestionEnregistreurTest extends KernelTestCase
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
            $this->connection->executeStatement('DELETE FROM pim_fiche_suggestion');
            $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
            $this->connection->executeStatement('DELETE FROM pim_lieu');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
        }
        parent::tearDown();
    }

    public function testUnReRunNeDoublonnePasEtRespecteLesDecisions(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $enregistreur = self::getContainer()->get(FicheSuggestionEnregistreur::class);
        $repository = self::getContainer()->get(FicheSuggestionRepository::class);

        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel idempotent');
        $em->persist($lieu);
        $em->flush();
        $fiche = $lieu->fiche();
        $proposition = new SuggestionProposee(SuggestionAction::RemplirChamp, 'lieu_chaine', 'Chaîne', null, 'Accor', null);

        // 1er passage : création.
        self::assertSame(1, $enregistreur->enregistrer($fiche, SuggestionSource::Wikidata, [$proposition]));
        $em->flush();

        // 2e passage : rafraîchit l'attente, aucune création, aucun doublon.
        self::assertSame(0, $enregistreur->enregistrer($fiche, SuggestionSource::Wikidata, [$proposition]));
        $em->flush();
        self::assertSame(1, $this->compte());

        // Décision prise puis re-run : ne recrée rien (respecte la décision, pas de violation de contrainte).
        $repository->findPourCle($fiche, SuggestionSource::Wikidata, 'lieu_chaine')?->ignorer('actor');
        $em->flush();
        self::assertSame(0, $enregistreur->enregistrer($fiche, SuggestionSource::Wikidata, [$proposition]));
        $em->flush();
        self::assertSame(1, $this->compte(), 'La ligne arbitrée n\'est pas doublée.');
    }

    private function compte(): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche_suggestion WHERE source = 'wikidata'");
    }
}
