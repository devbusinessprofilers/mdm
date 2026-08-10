<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $crawler = $client->request('GET', '/medias', ['onglet' => 'droits']);
        self::assertResponseIsSuccessful();
        $cartes = $crawler->filter('.dam-stats a.dam-stat');
        self::assertSame(9, $cartes->count(), 'Les neuf cartes d\'anomalies doivent être présentes.');

        foreach ($cartes as $carte) {
            $href = (string) $carte->getAttribute('href');
            self::assertStringContainsString('/medias?filter=', $href, 'Les cartes doivent rester sur /medias avec un paramètre filter.');
            $libelle = trim((string) $carte->getElementsByTagName('span')->item(0)?->textContent);
            $suivant = $client->request('GET', $href);
            self::assertResponseIsSuccessful($href);
            self::assertSame(
                $libelle,
                trim($suivant->filter('.dam-stat-active span')->text('')),
                sprintf('La carte « %s » doit devenir active après le clic.', $libelle),
            );
        }
    }
}
