<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les transitions de workflow depuis l'éditeur, mêmes routes
 * (`/referentiel/{gamme}/fiche/{id}/{segment}`), mêmes formulaires et mêmes
 * messages pour les quatre gammes : les jetons sont lus dans la page fiche,
 * qui ne propose que les actions du statut courant.
 */
#[Group('database')]
final class FicheWorkflowControllerTest extends WebTestCase
{
    private const TABLES = [
        'outbox_message', 'pim_ressource_lieu', 'pim_acces_lieu', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche_site_diffusion',
        'pim_fiche_administratif', 'pim_lieu_tarification', 'pim_lieu',
        'pim_restaurant_salle', 'pim_restaurant_periode_fermeture', 'pim_restaurant_acces', 'pim_restaurant',
        'pim_activite_offre', 'pim_activite', 'pim_service_evenementiel',
        'pim_fiche', 'pim_localisation', 'account_user',
    ];

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

    /** @return iterable<string, array{string, string}> */
    public static function gammes(): iterable
    {
        yield 'lieu' => ['lieux', 'lieu'];
        yield 'restaurant' => ['restaurants', 'restaurant'];
        yield 'activité' => ['activites', 'activite'];
        yield 'service' => ['services', 'service'];
    }

    #[DataProvider('gammes')]
    public function testLesTransitionsDuWorkflow(string $gamme, string $domaine): void
    {
        $client = $this->client('validateur-'.$gamme.'@example.test', ['ROLE_BP_VALIDATOR']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entite = self::nouvelle($gamme);
        $entite->changeLabel('Fiche workflow '.$gamme);
        $entite->fiche()->submitForValidation('test');
        $entityManager->persist($entite);
        $entityManager->flush();
        $id = $entite->id();
        $page = '/referentiel/'.$gamme.'/fiche/'.$id;

        // Refus sans motif : message, statut inchangé.
        $client->request('POST', $page.'/refuser', ['reject_'.$domaine => ['reason' => '', '_token' => $this->jeton($client, $page, 'reject_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Le motif du refus est obligatoire.');
        self::assertSame('en_attente_validation', $this->statut());

        // Sans jeton CSRF : rien ne bouge.
        $client->request('POST', $page.'/valider', ['validate_'.$domaine => []]);
        self::assertResponseRedirects($page);
        self::assertSame('en_attente_validation', $this->statut());

        // Valider.
        $client->request('POST', $page.'/valider', ['validate_'.$domaine => ['_token' => $this->jeton($client, $page, 'validate_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche validée.');
        self::assertSame('validee', $this->statut());

        // Publier : le Lieu incomplet est refusé avec le motif, les autres gammes passent.
        $client->request('POST', $page.'/publier', ['publish_'.$domaine => ['_token' => $this->jeton($client, $page, 'publish_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        if ('lieux' === $gamme) {
            self::assertSelectorTextContains('body', 'obligatoire');
            self::assertSame('validee', $this->statut());
            $this->connection->executeStatement("UPDATE pim_fiche SET status = 'publiee'");
        } else {
            self::assertSelectorTextContains('body', 'Fiche publiée.');
            self::assertSame('publiee', $this->statut());
        }

        // Archiver puis désarchiver (retour en cours).
        $client->request('POST', $page.'/archiver', ['archive_'.$domaine => ['_token' => $this->jeton($client, $page, 'archive_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche archivée.');
        self::assertSame('archivee', $this->statut());

        $client->request('POST', $page.'/desarchiver', ['unarchive_'.$domaine => ['_token' => $this->jeton($client, $page, 'unarchive_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche désarchivée.');
        self::assertSame('en_cours', $this->statut());

        // Soumettre une fiche incomplète : les violations sont listées, le statut ne change pas.
        $client->request('POST', $page.'/soumettre', ['submit_'.$domaine => ['_token' => $this->jeton($client, $page, 'submit_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'obligatoire');
        self::assertSame('en_cours', $this->statut());

        // Republier depuis « archivée ».
        $this->connection->executeStatement("UPDATE pim_fiche SET status = 'archivee'");
        $client->request('POST', $page.'/republier', ['republish_'.$domaine => ['_token' => $this->jeton($client, $page, 'republish_'.$domaine)]]);
        self::assertResponseRedirects($page);
        $client->followRedirect();
        if ('lieux' === $gamme) {
            self::assertSame('archivee', $this->statut());
        } else {
            self::assertSelectorTextContains('body', 'Fiche republiée.');
            self::assertSame('publiee', $this->statut());
        }

        // Supprimer : retour à la liste de la gamme, fiche disparue.
        $client->request('POST', $page.'/supprimer', ['delete_'.$domaine => ['_token' => $this->jeton($client, $page, 'delete_'.$domaine)]]);
        self::assertResponseRedirects('lieux' === $gamme ? '/referentiel/lieux' : '/referentiel/'.$gamme);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }

    public function testUnEditeurNePeutNiValiderNiSupprimer(): void
    {
        $client = $this->client('editeur-workflow@example.test', ['ROLE_BP_EDITOR']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $activite = new Activite();
        $activite->changeLabel('Activité protégée');
        $activite->fiche()->submitForValidation('test');
        $entityManager->persist($activite);
        $entityManager->flush();
        $page = '/referentiel/activites/fiche/'.$activite->id();

        $client->request('POST', $page.'/valider', ['validate_activite' => ['_token' => 'x']]);
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', $page.'/supprimer', ['delete_activite' => ['_token' => 'x']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('en_attente_validation', $this->statut());
    }

    private static function nouvelle(string $gamme): Lieu|Restaurant|Activite|ServiceEvenementiel
    {
        return match ($gamme) {
            'lieux' => new Lieu(),
            'restaurants' => new Restaurant(),
            'activites' => new Activite(),
            default => new ServiceEvenementiel(),
        };
    }

    /** Jeton CSRF du formulaire d'action rendu par la page fiche pour le statut courant. */
    private function jeton(KernelBrowser $client, string $page, string $formulaire): string
    {
        $crawler = $client->request('GET', $page);
        self::assertResponseIsSuccessful();
        $champ = $crawler->filter('input[name="'.$formulaire.'[_token]"]');
        self::assertCount(1, $champ, sprintf('La page doit proposer le formulaire %s.', $formulaire));

        return (string) $champ->attr('value');
    }

    private function statut(): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM pim_fiche');
    }

    /** @param list<string> $roles */
    private function client(string $email, array $roles): KernelBrowser
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        $user = new User($email, $roles);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function clearTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
