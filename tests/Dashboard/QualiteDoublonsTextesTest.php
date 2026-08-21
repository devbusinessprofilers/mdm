<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\DuplicateReviewStatus;
use App\Pim\Repository\TextDuplicateAlertRepository;
use App\Pim\Service\TextDuplicateDetector;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Onglet « Doublons de textes » de l'écran Qualité : liste les champs signalés
 * et permet de confirmer ou d'ignorer un doublon en un clic (validateurs).
 */
#[Group('database')]
final class QualiteDoublonsTextesTest extends WebTestCase
{
    private const DESC = 'Ce domaine viticole d exception propose des seminaires et du team building en pleine nature avec un hebergement de charme et une restauration gastronomique sur place.';

    public function testListeEtArbitrageDunDoublonDeTexte(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $connection = $container->get(Connection::class);
        foreach (['pim_text_duplicate_alert', 'pim_text_simhash_band', 'pim_text_fingerprint', 'pim_lieu', 'pim_fiche', 'account_user'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }

        $user = new User('qualite-doublons@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $em->persist($user);
        $reference = new Lieu();
        $reference->changeLabel('Domaine de référence');
        $reference->changeDescGenerale(self::DESC);
        $em->persist($reference);
        $copie = new Lieu();
        $copie->changeLabel('Domaine copieur');
        $copie->changeDescGenerale(self::DESC);
        $em->persist($copie);
        $em->flush();

        $detector = $container->get(TextDuplicateDetector::class);
        $detector->analyze($reference->fiche());
        $detector->analyze($copie->fiche());

        $client->loginUser($user);
        $crawler = $client->request('GET', '/qualite?onglet=doublons_textes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Doublons de textes');
        self::assertSelectorTextContains('table', 'Domaine copieur');

        $form = $crawler->filter('form[action*="/qualite/doublon-texte/"][action*="/ignorer"]')->first()->form();
        $client->submit($form);
        self::assertResponseRedirects();

        $em->clear();
        $alerts = $container->get(TextDuplicateAlertRepository::class)->findAll();
        self::assertCount(1, $alerts);
        self::assertSame(DuplicateReviewStatus::Resolved, $alerts[0]->status());
        self::assertNotNull($alerts[0]->reviewedBy());
    }
}
