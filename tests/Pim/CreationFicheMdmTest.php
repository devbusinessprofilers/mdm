<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class CreationFicheMdmTest extends WebTestCase
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

    public function testCreationDepuisLaRouteMdmAvecContactDeRepliEtSites(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('creation-mdm@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $obligatoire = new SiteDiffusion('BP_TEST', 'Business Profilers', 'Réseau', true, false, 0, []);
        $optionnel = new SiteDiffusion('KACTUS_TEST', 'Kactus', 'Partenaires', false, false, 1, [TypeFiche::Lieu]);
        $horsSelection = new SiteDiffusion('VENUU_TEST', 'Venuu', 'International', false, false, 2, []);
        $entityManager->persist($obligatoire);
        $entityManager->persist($optionnel);
        $entityManager->persist($horsSelection);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel/fiche/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer une fiche');
        // La présélection initiale coche l'obligatoire et le site par défaut.
        self::assertGreaterThanOrEqual(2, $crawler->filter('input[type="checkbox"][checked]')->count());

        $form = $crawler->selectButton('Créer la fiche')->form();
        $values = $form->getPhpValues();
        $values['fiche_creation']['type'] = 'lieu';
        $values['fiche_creation']['label'] = 'Lieu MDM avec repli';
        $values['fiche_creation']['localisation']['pays'] = 'France';
        $values['fiche_creation']['localisation']['codePostal'] = '75001';
        $values['fiche_creation']['localisation']['ville'] = 'Paris';
        $values['fiche_creation']['contactRepli'] = '1';
        $values['fiche_creation']['sitesDiffusion'] = [(string) $optionnel->id()];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();

        $lieu = $entityManager->getRepository(Lieu::class)->findOneBy([]);
        self::assertInstanceOf(Lieu::class, $lieu);
        $fiche = $lieu->fiche();

        // Sites retenus : le choix + l'obligatoire réappliqué, sans le troisième.
        self::assertEqualsCanonicalizing(
            [$obligatoire->id(), $optionnel->id()],
            $fiche->siteDiffusionIds(),
        );

        // Contact de repli : affiliation marquée, collaborateur service référencement.
        $affiliations = $entityManager->getRepository(FicheAffiliation::class)->findBy(['fiche' => $fiche->id()]);
        self::assertCount(1, $affiliations);
        self::assertTrue($affiliations[0]->repli());
        self::assertSame('referencement@businessprofilers.fr', $affiliations[0]->collaborateur()->email());

        // La facette « contact de repli » retrouve la fiche.
        $client->request('GET', '/referentiel', ['f' => ['repli' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Lieu MDM avec repli');
        self::assertSelectorTextContains('body', '1 dans le filtre');
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
