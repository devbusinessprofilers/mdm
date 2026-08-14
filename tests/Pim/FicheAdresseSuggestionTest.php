<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Bloc « Suggestions en attente » (onglet Informations générales) et écran
 * Qualité : l'écart d'adresse relevé par la BAN s'arbitre en un clic —
 * Accepter applique la proposition, Ignorer garde la saisie.
 */
#[Group('database')]
final class FicheAdresseSuggestionTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testAccepterAppliqueLaPropositionAuNiveauRue(): void
    {
        $lieu = $this->lieuAvecEcart([
            'label' => '24 Rue des Tests 49590 Fontevraud-l\'Abbaye',
            'name' => '24 Rue des Tests',
            'type' => 'housenumber',
            'codePostal' => '49590',
            'ville' => 'Fontevraud-l\'Abbaye',
            'latitude' => '47.18',
            'longitude' => '0.05',
        ]);

        // Le bloc apparaît dans Informations générales, même sans OCR actif.
        $crawler = $this->client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Suggestions en attente');
        self::assertSelectorTextContains('table', 'BAN');
        self::assertSelectorTextContains('table', '24 Rue des Tests');
        self::assertSelectorTextContains('table', '91 %');

        $this->client->submit($crawler->selectButton('Accepter')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'appliquée à la fiche');

        $ligne = $this->connection->fetchAssociative(
            'SELECT rue_postale, code_postal, ville, latitude, ban_ecart, ban_proposition FROM pim_localisation LIMIT 1',
        );
        self::assertIsArray($ligne);
        self::assertSame('24 Rue des Tests', $ligne['rue_postale']);
        self::assertSame('49590', $ligne['code_postal']);
        self::assertSame('Fontevraud-l\'Abbaye', $ligne['ville']);
        self::assertNotNull($ligne['latitude']);
        self::assertSame(0, (int) $ligne['ban_ecart']);
        self::assertNull($ligne['ban_proposition']);
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'), 'Pas de transition de workflow pour un validateur.');
    }

    public function testAccepterUneCommuneSeuleNEcrasePasLaRue(): void
    {
        $lieu = $this->lieuAvecEcart([
            'label' => 'Queyrac',
            'name' => 'Queyrac',
            'type' => 'municipality',
            'codePostal' => '33340',
            'ville' => 'Queyrac',
            'latitude' => '45.35',
            'longitude' => '-0.92',
        ]);

        $crawler = $this->client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString());
        $this->client->submit($crawler->selectButton('Accepter')->form());
        self::assertResponseRedirects();

        $ligne = $this->connection->fetchAssociative('SELECT rue_postale, code_postal, ville, ban_ecart FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        self::assertSame('12 Chemin Imaginaire', $ligne['rue_postale'], 'La rue saisie n\'est pas touchée sous le niveau rue.');
        self::assertSame('33340', $ligne['code_postal']);
        self::assertSame('Queyrac', $ligne['ville']);
        self::assertSame(0, (int) $ligne['ban_ecart']);
    }

    public function testIgnorerGardeLaSaisieEtSoldeLEcart(): void
    {
        $lieu = $this->lieuAvecEcart([
            'label' => 'Queyrac',
            'name' => null,
            'type' => 'municipality',
            'codePostal' => '33340',
            'ville' => 'Queyrac',
            'latitude' => null,
            'longitude' => null,
        ]);

        $crawler = $this->client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString());
        $this->client->submit($crawler->selectButton('Ignorer')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'la saisie actuelle est conservée');

        $ligne = $this->connection->fetchAssociative(
            'SELECT ville, ban_ecart, ban_proposition, ban_score FROM pim_localisation LIMIT 1',
        );
        self::assertIsArray($ligne);
        self::assertSame('Fontevraud-l\'Abbaye', $ligne['ville']);
        self::assertSame(0, (int) $ligne['ban_ecart']);
        self::assertNull($ligne['ban_proposition']);
        self::assertNotNull($ligne['ban_score'], 'La trace du passage BAN reste.');
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
    }

    public function testUneAdresseModifieeDepuisLaVerificationNestPasAcceptable(): void
    {
        $lieu = $this->lieuAvecEcart([
            'label' => 'Queyrac',
            'name' => null,
            'type' => 'municipality',
            'codePostal' => '33340',
            'ville' => 'Queyrac',
            'latitude' => null,
            'longitude' => null,
        ]);
        // L'adresse bouge après la vérification : l'empreinte ne colle plus.
        $localisation = $lieu->fiche()->localisation();
        self::assertNotNull($localisation);
        $localisation->changeVille('Saumur');
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString());
        $this->client->submit($crawler->selectButton('Accepter')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'a changé depuis la vérification');

        $ligne = $this->connection->fetchAssociative('SELECT ville, ban_ecart FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        self::assertSame('Saumur', $ligne['ville']);
        self::assertSame(1, (int) $ligne['ban_ecart']);
    }

    public function testLEcranQualiteArbitreAvecRetourSurPlace(): void
    {
        $this->lieuAvecEcart([
            'label' => 'Queyrac',
            'name' => null,
            'type' => 'municipality',
            'codePostal' => '33340',
            'ville' => 'Queyrac',
            'latitude' => null,
            'longitude' => null,
        ]);

        $crawler = $this->client->request('GET', '/qualite', ['onglet' => 'conflits']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Suggestions d\'adresse');
        self::assertSelectorTextContains('body', 'Queyrac');

        $this->client->submit($crawler->selectButton('Ignorer')->form());
        self::assertResponseRedirects('/qualite?onglet=conflits');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Aucun écart d\'adresse relevé par la BAN.');
    }

    /** @param array{label: ?string, name: ?string, type: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string} $proposition */
    private function lieuAvecEcart(array $proposition): Lieu
    {
        $user = new User('adresse@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $this->entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Abbaye des écarts');
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeRuePostale('12 Chemin Imaginaire');
        $localisation->changeCodePostal('49590');
        $localisation->changeVille('Fontevraud-l\'Abbaye');
        $lieu->changeLocalisation($localisation);
        $lieu->fiche()->publishForImport();
        // Trace laissée par la vérification continue : écart à arbitrer.
        $localisation->recordBanVerification(0.91, $proposition, true);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        return $lieu;
    }

    private function clear(): void
    {
        foreach ([
            'outbox_message',
            'audit_revision',
            'pim_fiche_search',
            'pim_fiche_site_diffusion',
            'pim_fiche_attribute_value',
            'pim_ressource_lieu',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
            'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
