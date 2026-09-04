<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Message\IndexFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\MessageHandler\IndexFicheHandler;
use App\Pim\MessageHandler\VerifierAdresseFicheHandler;
use App\Pim\Service\GeocodeurEtrangerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérification des adresses étrangères (Geoapify) : mêmes règles que la BAN
 * — enrichissements sûrs, écarts arbitrables — aiguillées par le pays.
 */
#[Group('database')]
final class VerifierAdresseEtrangereTest extends WebTestCase
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

    public function testUneAdresseAllemandeConcordanteEstEnrichieSansBoucle(): void
    {
        $lieu = $this->lieuAllemand();
        $code = (string) $lieu->fiche()->code();
        self::getContainer()->set(GeocodeurEtrangerInterface::class, new FakeGeocodeurEtranger([
            $code => [
                'score' => 0.95,
                'label' => 'Unter den Linden 1, 10117 Berlin, Germany',
                'name' => 'Unter den Linden 1',
                'codePostal' => '10117',
                'ville' => 'Berlin',
                'latitude' => '52.516',
                'longitude' => '13.378',
                'type' => 'housenumber',
            ],
        ]));

        // Toute mutation converge vers IndexFiche : adresse jamais vérifiée
        // et couverte par le géocodeur étranger → vérification enfilée.
        $indexHandler = self::getContainer()->get(IndexFicheHandler::class);
        $indexHandler(new IndexFiche($lieu->fiche()->idString()));
        self::assertSame(1, $this->outboxCount(VerifierAdresseFiche::class));

        $verifHandler = self::getContainer()->get(VerifierAdresseFicheHandler::class);
        $verifHandler(new VerifierAdresseFiche($lieu->fiche()->idString()));

        $ligne = $this->connection->fetchAssociative(
            'SELECT latitude, ban_score, ban_ecart, ban_fingerprint, address_fingerprint FROM pim_localisation LIMIT 1',
        );
        self::assertIsArray($ligne);
        self::assertNotNull($ligne['latitude'], 'GPS manquant rempli (enrichissement sûr).');
        self::assertSame(0.95, (float) $ligne['ban_score']);
        self::assertSame(0, (int) $ligne['ban_ecart']);
        self::assertSame($ligne['address_fingerprint'], $ligne['ban_fingerprint'], 'Empreinte capturée après enrichissement.');

        // Le re-passage par IndexFiche ne redéclenche rien : pas de boucle.
        $indexHandler(new IndexFiche($lieu->fiche()->idString()));
        self::assertSame(1, $this->outboxCount(VerifierAdresseFiche::class));
    }

    public function testUnEcartEtrangerRemonteAvecLaSourceGeoapify(): void
    {
        $lieu = $this->lieuAllemand();
        $code = (string) $lieu->fiche()->code();
        self::getContainer()->set(GeocodeurEtrangerInterface::class, new FakeGeocodeurEtranger([
            $code => [
                'score' => 0.9,
                'label' => 'Unter den Linden 1, 14467 Potsdam, Germany',
                'name' => 'Unter den Linden 1',
                'codePostal' => '14467',
                'ville' => 'Potsdam',
                'latitude' => '52.4',
                'longitude' => '13.06',
                'type' => 'housenumber',
            ],
        ]));

        $verifHandler = self::getContainer()->get(VerifierAdresseFicheHandler::class);
        $verifHandler(new VerifierAdresseFiche($lieu->fiche()->idString()));

        $ligne = $this->connection->fetchAssociative('SELECT ville, ban_ecart, ban_proposition FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        self::assertSame('Berlin', $ligne['ville'], 'Une ville divergente n\'est jamais écrasée.');
        self::assertSame(1, (int) $ligne['ban_ecart']);
        self::assertStringContainsString('Potsdam', (string) $ligne['ban_proposition']);

        $user = new User('etranger@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        // Bloc de la fiche : source Geoapify + attribution OSM.
        $this->client->request('GET', '/referentiel/lieux/fiche/'.$lieu->fiche()->idString());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-suggestions-attente]', 'Geoapify');
        self::assertSelectorTextContains('[data-suggestions-attente]', 'Potsdam');
        self::assertSelectorTextContains('body', 'OpenStreetMap contributors');

        // Écran Qualité : même source.
        $this->client->request('GET', '/qualite', ['onglet' => 'conflits']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Geoapify');
        self::assertSelectorTextContains('body', 'Potsdam');
    }

    public function testSansClientConfigureLAdresseEtrangereNestPasVerifiee(): void
    {
        $lieu = $this->lieuAllemand();
        self::getContainer()->set(GeocodeurEtrangerInterface::class, new FakeGeocodeurEtranger([], configured: false));

        $indexHandler = self::getContainer()->get(IndexFicheHandler::class);
        $indexHandler(new IndexFiche($lieu->fiche()->idString()));
        self::assertSame(0, $this->outboxCount(VerifierAdresseFiche::class), 'Pas de message sans consommateur configuré.');
    }

    private function lieuAllemand(): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel des vérifications');
        $localisation = new Localisation();
        $localisation->changePays('Allemagne');
        $localisation->changeCountryCode('DE');
        $localisation->changeRuePostale('Unter den Linden 1');
        $localisation->changeCodePostal('10117');
        $localisation->changeVille('Berlin');
        $lieu->changeLocalisation($localisation);
        $lieu->fiche()->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $lieu;
    }

    private function outboxCount(string $messageType): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
            [$messageType],
        );
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
            'pim_fiche_administratif',
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
