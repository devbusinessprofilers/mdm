<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** app:fiches:validate remplace les quatre commandes par gamme : même sortie JSON, filtre `--gamme`. */
#[Group('database')]
final class ValidateFichesCommandTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
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

    public function testLesViolationsSontListeesParGammeEtLeFiltreLesRestreint(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $activite = new Activite();
        $activite->changeLabel('Activité au minimum négatif');
        $activite->changeParticipantsMin(-3);
        $entityManager->persist($activite);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Service valide en brouillon');
        $entityManager->persist($service);
        $entityManager->flush();
        $entityManager->clear();

        // Toutes les gammes : l'activité invalide fait échouer la commande.
        $tester = $this->tester();
        self::assertSame(Command::FAILURE, $tester->execute([]));
        $sortie = $tester->getDisplay();
        self::assertStringContainsString('"gamme":"activites"', $sortie);
        self::assertStringContainsString('"id":"'.$activite->id().'"', $sortie);
        self::assertStringContainsString('"field":"participantsMin"', $sortie);
        self::assertStringContainsString('Activités : 1 fiche(s) contrôlée(s), 1 invalide(s)', $sortie);
        self::assertStringContainsString('Services : 1 fiche(s) contrôlée(s), 0 invalide(s)', $sortie);

        // Restreinte aux services : rien à signaler.
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--gamme' => 'services']));
        self::assertStringNotContainsString('"gamme":"activites"', $tester->getDisplay());

        // Gamme inconnue : refus explicite.
        $tester = $this->tester();
        self::assertSame(Command::INVALID, $tester->execute(['--gamme' => 'traiteurs']));
        self::assertStringContainsString('Gamme inconnue', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? self::bootKernel());

        return new CommandTester($application->find('app:fiches:validate'));
    }

    private function clearTables(): void
    {
        foreach ([
            'pim_ressource_lieu', 'pim_activite_offre', 'pim_activite', 'pim_service_evenementiel',
            'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
