<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Les modales de paramètres des médias sont préchargées en arrière-plan : la
 * page fiche ne porte que les vignettes et l'URL de préchargement, l'endpoint
 * app_pim_lieu_media_modales rend toutes les modales d'un coup.
 */
#[Group('database')]
final class LieuMediaModalesControllerTest extends WebTestCase
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

    public function testLesModalesSontServiesAPartEtAbsentesDeLaPage(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('editeur-medias@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir des médias');
        $resource = new RessourceLieu();
        $resource->changeDamAssetId('asset-inexistant');
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($resource);
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        // La page fiche montre la vignette et l'URL de préchargement, mais plus
        // la modale ni son formulaire de métadonnées.
        $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id().'?section=11');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-lieu-media-modales-url-value]');
        self::assertSelectorExists('[data-modal-trigger-button-modal-identifier-value="photo-'.$resource->id().'"]');
        self::assertSelectorNotExists('[data-modal="photo-'.$resource->id().'"]');
        self::assertSelectorNotExists('form[name="photo_metadata_'.$resource->id().'"]');

        // L'endpoint rend la modale complète, fermée (ouverte au clic comme avant).
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id().'/medias/modales');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-modal="photo-'.$resource->id().'"]');
        self::assertSame(1, $crawler->filter('form[name="photo_metadata_'.$resource->id().'"]')->count());
        self::assertSame('false', $crawler->filter('[data-modal="photo-'.$resource->id().'"]')->attr('data-modal-display-value'));

        // Le recadrage ne se saisit plus à la main : champs cachés alimentés
        // par la modale de recadrage, absente ici faute d'asset DAM résolu.
        self::assertSame(1, $crawler->filter('input[type="hidden"][name="photo_metadata_'.$resource->id().'[crop_x]"]')->count());
        self::assertSame(0, $crawler->filter('input[type="number"]')->count());
        self::assertSame(0, $crawler->filter('select[name="photo_metadata_'.$resource->id().'[rotation]"]')->count());
        self::assertSelectorNotExists('[data-modal="crop-photo-'.$resource->id().'"]');
    }

    public function testLaModaleDeRecadrageEstRendueQuandLAssetExiste(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('editeur-recadrage@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $asset = new MediaAsset(new Ulid(), 'originals/photo-recadrage.jpg', 'photo-recadrage.jpg', 'image/jpeg', 456, str_repeat('c', 64));
        $entityManager->persist($asset);

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir du recadrage');
        $resource = new RessourceLieu();
        $resource->changeDamAssetId((string) $asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($resource);
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id().'/medias/modales');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-modal-trigger-button-modal-identifier-value="crop-photo-'.$resource->id().'"]');
        $cropModal = $crawler->filter('[data-modal="crop-photo-'.$resource->id().'"]');
        self::assertSame(1, $cropModal->count());
        self::assertStringContainsString('/photos/'.$resource->id().'/original', (string) $cropModal->attr('data-crop-modal-original-url-value'));
        self::assertSame('photo_metadata_'.$resource->id(), $cropModal->attr('data-crop-modal-form-name-value'));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM dam_media_rendition');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
