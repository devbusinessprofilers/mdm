<?php

declare(strict_types=1);

namespace App\Tests\Vision;

use App\Account\Entity\User;
use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\NatureRessource;
use App\Shared\Entity\Parametre;
use App\Shared\Enum\TypeParametre;
use App\Shared\Service\ParametreProvider;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class VisionControllersTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testIaDesactiveeLesOngletsExpliquentEtLesActionsFont404(): void
    {
        $client = $this->clientValidateur();

        $client->request('GET', '/medias', ['onglet' => 'import']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Retouche IA désactivée');

        $client->request('GET', '/medias', ['onglet' => 'ia']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Reconnaissance IA désactivée');

        $client->request('POST', '/medias/retouche/'.new Ulid().'/accepter');
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/medias/reco/lancer');
        self::assertResponseStatusCodeSame(404);
    }

    public function testIaActiveeLaDecisionExigeUnJetonCsrfPuisAccepteLaRetouche(): void
    {
        $client = $this->clientValidateur();
        $this->activerOpenAi();
        $enhancement = $this->retouchePrete();

        $crawler = $client->request('GET', '/medias', ['onglet' => 'import']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'À valider');

        $client->request('POST', '/medias/retouche/'.$enhancement->id().'/accepter', ['_token' => 'invalide']);
        self::assertResponseStatusCodeSame(403);

        $client->submit($crawler->selectButton('Accepter')->form());
        self::assertResponseRedirects('/medias?onglet=import&page=1');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Retouche acceptée');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(ImageEnhancement::class, $enhancement->id());
        self::assertNotNull($reloaded);
        self::assertSame(EnhancementStatus::Accepted, $reloaded->status());
        self::assertTrue($reloaded->media()->isEnhanced());
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
                ['App\Vision\Message\ApplyImageEnhancement'],
            ),
        );
    }

    private function clientValidateur(): KernelBrowser
    {
        $client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clear();
        $user = new User('vision@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function activerOpenAi(): void
    {
        $parametre = new Parametre('openai.actif', 'Test : activation IA', TypeParametre::Booleen);
        $parametre->surcharger('1');
        $this->entityManager->persist($parametre);
        $this->entityManager->flush();
        self::getContainer()->get(ParametreProvider::class)->invalider();
    }

    private function retouchePrete(): ImageEnhancement
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château vision');
        $localisation = new Localisation();
        $localisation->changeVille('Paris');
        $lieu->changeLocalisation($localisation);
        $fiche = $lieu->fiche();
        $id = new Ulid();
        $asset = new MediaAsset($id, 'dev/photos/originals/'.$id.'/original.jpg', 'original.jpg', 'image/jpeg', 1024, str_repeat('a', 64));
        $asset->addRendition(new MediaRendition($asset, 'small', 'dev/photos/small/'.$id.'.webp', 200, 100, 64));
        $asset->markProcessed();
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $fiche->addResource($resource);
        $enhancement = new ImageEnhancement($fiche, $asset, $resource, 'Prompt.', 'gpt-image-test', 'editor');
        $enhancement->start();
        $enhancement->complete('dev/photos/originals/'.$id.'/retouche/'.$enhancement->id().'.png', str_repeat('b', 64), 2048, null);
        $this->entityManager->persist($asset);
        $this->entityManager->persist($resource);
        $this->entityManager->persist($lieu);
        $this->entityManager->persist($enhancement);
        $this->entityManager->flush();

        return $enhancement;
    }

    private function clear(): void
    {
        foreach ([
            'vision_image_recognition_suggestion',
            'vision_image_recognition',
            'vision_image_enhancement',
            'outbox_message',
            'parametre',
            'pim_ressource_lieu',
            'dam_media_duplicate_alert',
            'dam_media_rendition',
            'dam_media_asset',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
            'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
        self::getContainer()->get(ParametreProvider::class)->invalider();
    }
}
