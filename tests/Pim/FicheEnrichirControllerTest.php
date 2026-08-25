<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Message\EnrichirFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

#[Group('database')]
final class FicheEnrichirControllerTest extends WebTestCase
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
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testLeBoutonEnfileLEnrichissementDeLaFiche(): void
    {
        $client = self::createClient();
        // Sans ça le kernel reboote entre les requêtes et vide le transport
        // in-memory avant l'assertion.
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('editeur-enrichir@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château à enrichir');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Enrichir ce qui manque')->form());
        self::assertResponseRedirects();

        // Le message part sur le transport dédié aux traitements longs. À
        // inspecter AVANT followRedirect : les services (donc le transport
        // in-memory) sont réinitialisés entre deux requêtes.
        $transport = self::getContainer()->get('messenger.transport.enrichment');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $enfiles = array_filter(
            $transport->getSent(),
            static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof EnrichirFiche,
        );
        self::assertCount(1, $enfiles);

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Enrichissement lancé');

        // La demande est tracée dès le clic, en attente du worker…
        $run = $this->connection->fetchAssociative('SELECT finished_at FROM pim_fiche_enrichment_run');
        self::assertIsArray($run);
        self::assertNull($run['finished_at']);

        // …et visible « en file » dans le journal /outils.
        $client->request('GET', '/outils?famille=enrichissement');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Enrichir ce qui manque · Château à enrichir');
        self::assertSelectorTextContains('body', 'En file');
    }

    private function clearTables(): void
    {
        foreach ([
            'outbox_message',
            'pim_fiche_enrichment_run',
            'pim_fiche_search',
            'pim_fiche_attribute_value',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
