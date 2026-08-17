<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'édition rapide vit dans une turbo-frame : ouverte depuis la liste elle
 * s'affiche en modale, ouverte directement elle occupe la page. Enregistrer
 * recharge la même fiche dans la frame (jamais la liste, qui ne porte pas la
 * frame) ; « Enregistrer et passer à la suivante » avance dans le filtre.
 */
#[Group('database')]
final class EditionRapideModaleTest extends WebTestCase
{
    public function testLaPagePorteLaFrameEtLesDeuxBoutons(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM pim_lieu');
        $connection->executeStatement('DELETE FROM pim_fiche');
        $connection->executeStatement('DELETE FROM account_user');

        $user = new User('edition-rapide@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $lieu = new Lieu();
        $lieu->changeLabel('Château modale');
        $entityManager->persist($lieu);
        $autre = new Lieu();
        $autre->changeLabel('Manoir voisin');
        $entityManager->persist($autre);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/referentiel/fiche/'.$lieu->fiche()->idString().'/edition-rapide');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#edition-rapide');
        self::assertSelectorTextContains('h1', 'Château modale');
        self::assertSelectorExists('button[name="form[enregistrer]"]');
        self::assertSelectorExists('button[name="form[enregistrerSuivante]"]');
        // La liste porte la coquille de la modale et le crayon branché dessus.
        $crawler = $client->request('GET', '/referentiel');
        self::assertGreaterThan(0, $crawler->filter('[data-edition-rapide-target="modale"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[data-action="edition-rapide#ouvrir"]')->count());
    }

    public function testEnregistrerResteSurLaFicheEtSuivanteAvance(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM pim_lieu');
        $connection->executeStatement('DELETE FROM pim_fiche');
        $connection->executeStatement('DELETE FROM account_user');

        $user = new User('edition-rapide-2@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        // « Suivante » = fiche modifiée moins récemment : créée en premier.
        $suivante = new Lieu();
        $suivante->changeLabel('Manoir suivant');
        $entityManager->persist($suivante);
        $entityManager->flush();
        $lieu = new Lieu();
        $lieu->changeLabel('Château modale');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $client->loginUser($user);
        $url = '/referentiel/fiche/'.$lieu->fiche()->idString().'/edition-rapide';

        // « Enregistrer » : la frame recharge la même fiche.
        $crawler = $client->request('GET', $url);
        $client->submit($crawler->selectButton('Enregistrer')->form());
        self::assertResponseRedirects($url);

        // « Enregistrer et passer à la suivante » : la frame avance dans le filtre.
        $crawler = $client->request('GET', $url);
        $client->submit($crawler->selectButton('Enregistrer et passer à la suivante')->form());
        self::assertResponseRedirects('/referentiel/fiche/'.$suivante->fiche()->idString().'/edition-rapide');
    }
}
