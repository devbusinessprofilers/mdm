<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class SiteDiffusionAdminTest extends WebTestCase
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

    public function testUnAdminAdministreLesSitesDeDiffusion(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('sites-admin@example.test', ['ROLE_ADMIN']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        // L'écran des LOV pointe vers le référentiel des sites.
        $crawler = $client->request('GET', '/admin/listes-de-valeurs');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/sites-de-diffusion"]')->count());

        // Création.
        $crawler = $client->request('GET', '/admin/sites-de-diffusion/ajouter');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['site_diffusion']['code'] = 'NOUVEAU_SITE_TEST';
        $values['site_diffusion']['label'] = 'Nouveau site';
        $values['site_diffusion']['groupe'] = 'Partenaires MICE';
        $values['site_diffusion']['position'] = '4';
        $values['site_diffusion']['payant'] = '1';
        $values['site_diffusion']['actif'] = '1';
        $values['site_diffusion']['gammesParDefaut'] = ['lieu'];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/admin/sites-de-diffusion');

        $sites = self::getContainer()->get(SiteDiffusionRepository::class);
        $site = $sites->findOneByCode('NOUVEAU_SITE_TEST');
        self::assertInstanceOf(SiteDiffusion::class, $site);
        self::assertTrue($site->payant());
        self::assertSame([TypeFiche::Lieu], $site->gammesParDefaut());

        // Modification : bascule obligatoire + désactivation.
        $crawler = $client->request('GET', '/admin/sites-de-diffusion/'.$site->id().'/modifier');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['site_diffusion']['label'] = 'Site renommé';
        $values['site_diffusion']['obligatoire'] = '1';
        unset($values['site_diffusion']['actif']);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/admin/sites-de-diffusion');

        $entityManager->clear();
        $modifie = $sites->findOneByCode('NOUVEAU_SITE_TEST');
        self::assertInstanceOf(SiteDiffusion::class, $modifie);
        self::assertSame('Site renommé', $modifie->label());
        self::assertTrue($modifie->obligatoire());
        self::assertFalse($modifie->actif());

        // La liste affiche le site.
        $client->request('GET', '/admin/sites-de-diffusion');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Site renommé');
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
