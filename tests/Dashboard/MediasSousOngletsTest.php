<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Régression : les cartes de statistiques (sous-onglets) du contenu DAM
 * réutilisé sur /medias doivent naviguer et activer la bonne carte.
 */
#[Group('database')]
final class MediasSousOngletsTest extends WebTestCase
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
            $this->connection->executeStatement('DELETE FROM account_user');
        }

        parent::tearDown();
    }

    public function testChaqueCarteNavigueEtActiveLaBonneEntree(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM account_user');
        $user = new User('sous-onglets@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // Chaque onglet à file expose ses pastilles ; chacune navigue sur
        // /medias?filter=… et devient active à l'arrivée.
        $attendues = ['biblio' => 2, 'droits' => 5, 'doublons' => 1, 'sync' => 3];

        foreach ($attendues as $onglet => $nombre) {
            $crawler = $this->contenu($client, '/medias?onglet='.$onglet);
            $pastilles = $crawler->filter('a[data-file]');
            self::assertSame($nombre, $pastilles->count(), sprintf('L\'onglet « %s » doit afficher %d files.', $onglet, $nombre));

            foreach ($pastilles as $pastille) {
                if (!$pastille instanceof \DOMElement) {
                    self::fail('Chaque pastille doit être un élément DOM.');
                }
                $href = (string) $pastille->getAttribute('href');
                $cle = (string) $pastille->getAttribute('data-file');
                self::assertStringContainsString('/medias?filter=', $href, 'Les pastilles doivent rester sur /medias avec un paramètre filter.');
                $suivant = $this->contenu($client, $href);
                self::assertSame(
                    $cle,
                    $suivant->filter('a[data-file-active]')->attr('data-file'),
                    sprintf('La file « %s » doit devenir active après le clic.', $cle),
                );
            }
        }
    }

    /**
     * Le contenu est différé dans une frame Turbo : la coquille répond, porte
     * la frame, et son src rend les pastilles — comme le fait le navigateur.
     */
    private function contenu(KernelBrowser $client, string $url): Crawler
    {
        $coquille = $client->request('GET', $url);
        self::assertResponseIsSuccessful($url);
        $src = $coquille->filter('turbo-frame#medias-contenu')->attr('src');
        self::assertNotNull($src, sprintf('La coquille %s doit porter une frame de contenu à charger.', $url));
        $contenu = $client->request('GET', $src);
        self::assertResponseIsSuccessful($src);

        return $contenu;
    }
}
