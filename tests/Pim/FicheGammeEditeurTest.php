<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Listes et éditeurs par gamme (Restaurant, Activité, Service) sur le patron du Lieu. */
#[Group('database')]
final class FicheGammeEditeurTest extends WebTestCase
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

    public function testListesEtEditeursDesTroisGammes(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('gammes@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot des gammes');
        $restaurant->changeSiteOfficiel('https://bistrot.test');
        $entityManager->persist($restaurant);
        $activite = new Activite();
        $activite->changeLabel('Escalade des gammes');
        $entityManager->persist($activite);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Traiteur événementiel des gammes');
        $entityManager->persist($service);
        // Assez d'activités pour déclencher la pagination (14 par page).
        for ($i = 1; $i <= 15; ++$i) {
            $autre = new Activite();
            $autre->changeLabel('Activité de pagination '.$i);
            $entityManager->persist($autre);
        }
        $entityManager->flush();
        $client->loginUser($user);

        // Les listes filtrées par gamme ne montrent que leur gamme.
        $client->request('GET', '/referentiel/restaurants');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Restaurants');
        self::assertSelectorTextContains('table', 'Bistrot des gammes');
        self::assertSelectorTextNotContains('table', 'Escalade des gammes');
        // La pagination d'une liste par gamme conserve le paramètre {gamme}.
        $crawler = $client->request('GET', '/referentiel/activites');
        self::assertResponseIsSuccessful();
        $suivante = $crawler->filter('a:contains("Page suivante")');
        self::assertGreaterThan(0, $suivante->count(), 'La pagination doit apparaître avec 16 activités.');
        self::assertStringStartsWith('/referentiel/activites', (string) $suivante->attr('href'));
        $client->request('GET', (string) $suivante->attr('href'));
        self::assertResponseIsSuccessful();
        $client->request('GET', '/referentiel/services');
        self::assertSelectorTextContains('table', 'Traiteur événementiel des gammes');

        // Éditeur Restaurant : rail de sections, soumission partielle du label
        // sans toucher au reste (le site officiel, hors requête, doit survivre).
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bistrot des gammes');
        self::assertStringStartsWith('RES-', $crawler->filter('.page-description')->first()->text(null, true));
        // L'éditeur est la vue unique : un validateur y voit la suppression.
        self::assertSelectorExists('.danger-form');
        // Section Médias & menus : galerie au design de la fiche Lieu —
        // photos de la fiche et tuiles de documents.
        $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id().'?section=7');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Photos de la fiche');
        self::assertSelectorTextContains('main', 'Documents');
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id());
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $values['restaurant']['label'] = 'Bistrot renommé';
        // Soumission partielle stricte : seul le libellé voyage.
        unset($values['restaurant']['siteOfficiel'], $values['restaurant']['youtubeUrl']);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $entityManager->clear();
        $recharge = $entityManager->find(Restaurant::class, $restaurant->id());
        self::assertInstanceOf(Restaurant::class, $recharge);
        self::assertSame('Bistrot renommé', $recharge->label());
        self::assertSame('https://bistrot.test', $recharge->siteOfficiel());

        // Les éditeurs Activité et Service rendent avec leurs sections.
        $crawler = $client->request('GET', '/referentiel/activites/fiche/'.$activite->id());
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(7, $crawler->filter('nav[aria-label="Sections de la fiche"] li')->count());
        $client->request('GET', '/referentiel/services/fiche/'.$service->id().'?section=1');
        self::assertResponseIsSuccessful();
        // Le rail des sections vit désormais dans son propre créneau (coquille front),
        // hors du <main> : on y retrouve le titre de la section « Localisation & zone
        // d'intervention », preuve que l'éditeur Service rend bien toutes ses sections.
        self::assertSelectorTextContains('nav[aria-label="Sections de la fiche"]', 'zone d\'intervention');
    }

    // Le dépôt de supports commerciaux passe par le formulaire principal de la
    // fiche : la soumission partielle doit fusionner $request->files, sinon les
    // fichiers sont ignorés en silence (fiche « enregistrée », aucun document).
    public function testDepotSupportCommercialParLaSectionMedias(): void
    {
        $client = self::createClient();
        // Le stub de stockage doit survivre aux deux requêtes (GET puis POST) :
        // sans ça, le reboot du kernel entre les requêtes le remplace par le
        // vrai client S3, qui échoue en FilesystemException.
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        self::getContainer()->set(PrivateObjectStorageInterface::class, new class implements PrivateObjectStorageInterface {
            public function write(string $key, string $contents, array $options = []): void
            {
            }

            public function writeStream(string $key, mixed $stream, array $options = []): void
            {
            }

            public function read(string $key): string
            {
                return '';
            }

            public function readStream(string $key): mixed
            {
                $stream = fopen('php://temp', 'r+b');
                if (false === $stream) {
                    throw new \RuntimeException('Flux temporaire indisponible.');
                }

return $stream;
            }

            public function exists(string $key): bool
            {
                return false;
            }

            public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
            {
                return 'https://private.example.test/'.$key;
            }

            public function delete(string $key): void
            {
            }

            public function deleteDirectory(string $prefix): void
            {
            }
        });

        $user = new User('support@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Service à supports');
        $entityManager->persist($service);
        $entityManager->flush();
        $client->loginUser($user);

        $pdf = tempnam(sys_get_temp_dir(), 'mdm-support-');
        self::assertIsString($pdf);
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $crawler = $client->request('GET', '/referentiel/services/fiche/'.$service->id());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $nom = (string) array_key_first($values);
        $values[$nom]['supportTitle'] = 'Plaquette 2026';
        $values[$nom]['supportSource'] = 'Prestataire';
        $fichiers = [$nom => ['supportsCommerciaux' => [
            new UploadedFile($pdf, 'plaquette.pdf', 'application/pdf', null, true),
        ]]];
        $client->request($form->getMethod(), $form->getUri(), $values, $fichiers);
        self::assertResponseRedirects();
        @unlink($pdf);

        $document = $this->connection->fetchAssociative(
            'SELECT nature, usage_code, legende, source FROM pim_ressource_lieu',
        );
        self::assertIsArray($document, 'Le support déposé doit créer une ressource document.');
        self::assertSame('document', $document['nature']);
        self::assertSame('PJ_SUPPORT_COMMERCIAUX', $document['usage_code']);
        self::assertSame('Plaquette 2026', $document['legende']);
        self::assertSame('Prestataire', $document['source']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dam_media_asset'));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM dam_media_duplicate_alert');
        $this->connection->executeStatement('DELETE FROM dam_media_rendition');
        $this->connection->executeStatement('DELETE FROM dam_media_phash_band');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_salle');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_acces');
        $this->connection->executeStatement('DELETE FROM pim_activite_offre');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
