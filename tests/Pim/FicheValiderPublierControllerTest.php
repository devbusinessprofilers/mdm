<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Tests\Support\LieuComplet;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class FicheValiderPublierControllerTest extends WebTestCase
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

    public function testUneFicheConformeEstValideeEtPublieeEnUnClic(): void
    {
        $client = $this->clientValidateur();
        $lieu = $this->lieuEnAttente(avecPhotos: true);

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Valider et publier')->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche validée et publiée.');
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
    }

    public function testUneFicheSansPhotosConformesResteValideeAvecLeMotif(): void
    {
        $client = $this->clientValidateur();
        $lieu = $this->lieuEnAttente(avecPhotos: false);

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        $client->submit($crawler->selectButton('Valider et publier')->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'obligations photos non satisfaites');
        self::assertSame('validee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
    }

    public function testUneFicheIncompleteResteValideeAvecLesChampsManquants(): void
    {
        $client = $this->clientValidateur();
        $lieu = $this->lieuEnAttente(avecPhotos: true, complet: false);

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        $client->submit($crawler->selectButton('Valider et publier')->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Champs obligatoires manquants : Typologie');
        self::assertSelectorTextContains('body', 'Capacité maximale en configuration assise');
        self::assertSame('validee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
    }

    public function testLeBoutonNExisteQuEnAttenteDeValidation(): void
    {
        $client = $this->clientValidateur();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Château en cours');
        $entityManager->persist($lieu);
        $entityManager->flush();

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('form[name="valider_publier_fiche"]')->count());
    }

    private function clientValidateur(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        $user = new User('validateur-publier@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function lieuEnAttente(bool $avecPhotos, bool $complet = true): Lieu
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Château à publier');
        if ($complet) {
            // Champs obligatoires de la bible : la publication les exige.
            LieuComplet::completer($lieu);
        }
        if ($avecPhotos) {
            // Obligations photos du type Lieu : minimum atteint + principale.
            for ($i = 0; $i < 4; ++$i) {
                $resource = new RessourceLieu();
                $resource->changeDamAssetId((string) new Ulid());
                $resource->changeNature(NatureRessource::Photo);
                $resource->changeUsage(0 === $i ? 'PHOTO_PRINCIPALE' : 'PHOTO_DIVERSE');
                $resource->changePosition($i + 1);
                $lieu->fiche()->addResource($resource);
            }
        }
        $lieu->fiche()->submitForValidation('test');
        $entityManager->persist($lieu);
        $entityManager->flush();

        return $lieu;
    }

    private function clearTables(): void
    {
        foreach ([
            'outbox_message',
            'pim_ressource_lieu',
            'pim_acces_lieu',
            'pim_fiche_search',
            'pim_fiche_attribute_value',
            'pim_fiche_site_diffusion',
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
