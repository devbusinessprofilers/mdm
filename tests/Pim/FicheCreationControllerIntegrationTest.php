<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Lov\LieuLovCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheCreationControllerIntegrationTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->entityManager->clear();
            $this->cleanDatabase();
        }
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        foreach ([
            'outbox_message',
            'pim_fiche_search',
            'pim_fiche_attribute_value',
            'pim_fiche_affiliation',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_restaurant',
            'pim_activite',
            'pim_service_evenementiel',
            'pim_fiche',
            'pim_localisation',
            'pim_fiche_collaborateur',
            'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }

    private function createClientWithUser(): KernelBrowser
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->cleanDatabase();

        $user = new User('createur-fiche@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testCreatesAFicheOfEachGammeWithStatutEnCours(): void
    {
        $client = $this->createClientWithUser();
        $expectations = [
            ['lieu', 'pim_lieu', '/referentiel/lieux/fiche/'],
            ['restaurant', 'pim_restaurant', '/referentiel/restaurants/fiche/'],
            ['activite', 'pim_activite', '/referentiel/activites/fiche/'],
            ['service_evenementiel', 'pim_service_evenementiel', '/referentiel/services/fiche/'],
        ];
        foreach ($expectations as [$type, $table, $editPrefix]) {
            $client->request('GET', '/referentiel/fiche/nouvelle?type='.$type);
            self::assertResponseIsSuccessful();
            $client->submitForm('Créer la fiche', [
                'fiche_creation[type]' => $type,
                'fiche_creation[label]' => 'Fiche '.$type,
            ]);
            $location = (string) $client->getResponse()->headers->get('Location');
            self::assertStringStartsWith($editPrefix, $location, 'Redirection vers l’éditeur '.$type);
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table));
            self::assertSame(
                'en_cours',
                $this->connection->fetchOne('SELECT status FROM pim_fiche WHERE type = ?', [$type]),
            );
        }
    }

    public function testLieuCreationStoresLocalisationReferencementAndVisibilite(): void
    {
        $client = $this->createClientWithUser();
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Château des tests',
            'fiche_creation[localisation][pays]' => 'France',
            'fiche_creation[localisation][codePostal]' => '60500',
            'fiche_creation[localisation][ville]' => 'Chantilly',
            'fiche_creation[lieuTypologie]' => ['GENERALE_TYPOLOGIE_6'],
            'fiche_creation[businessPremium]' => '1',
            'fiche_creation[sitePremium]' => ['SITE_PREMIUM_1', 'SITE_PREMIUM_11'],
        ]);
        self::assertResponseRedirects();

        self::assertSame('Chantilly', $this->connection->fetchOne('SELECT ville FROM pim_localisation'));
        // Le niveau de statut (MICE_STATUT) ne se choisit plus à la création,
        // seul l'interrupteur Business Premium y figure.
        self::assertNull($this->connection->fetchOne('SELECT mice_statut FROM pim_lieu'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT business_premium FROM pim_fiche'));
        $valueIds = array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT value_id FROM pim_fiche_attribute_value WHERE attribute_code = ? ORDER BY value_id',
            ['SITE_PREMIUM'],
        ));
        $expected = [
            LieuLovCatalog::valueId('SITE_PREMIUM', 'SITE_PREMIUM_1'),
            LieuLovCatalog::valueId('SITE_PREMIUM', 'SITE_PREMIUM_11'),
        ];
        sort($expected);
        self::assertSame($expected, $valueIds);
        self::assertSame(
            [],
            $this->connection->fetchFirstColumn(
                'SELECT value_id FROM pim_fiche_attribute_value WHERE attribute_code = ?',
                ['MICE_STATUT'],
            ),
        );
    }

    public function testHiddenSectionsOfOtherGammesAreNeverPersisted(): void
    {
        $client = $this->createClientWithUser();
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'restaurant',
            'fiche_creation[label]' => 'Restaurant strict',
            'fiche_creation[lieuTypologie]' => ['GENERALE_TYPOLOGIE_6'],
            'fiche_creation[modeIntervention]' => 'mobile',
        ]);
        self::assertResponseRedirects();

        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche_attribute_value WHERE attribute_code = ?',
            ['GENERALE_TYPOLOGIE'],
        ));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_restaurant'));
    }

    public function testAffiliatesAnExistingCollaborateurAsManager(): void
    {
        $client = $this->createClientWithUser();
        $collaborateur = new FicheCollaborateur('manager@example.test', 'Ada', 'Lovelace');
        $this->entityManager->persist($collaborateur);
        $this->entityManager->flush();

        $crawler = $client->request('GET', '/referentiel/fiche/nouvelle');
        $autocompleteField = $crawler->filter('select[name="fiche_creation[collaborateurExistant]"]');
        self::assertCount(1, $autocompleteField);
        $autocompleteUrl = (string) $autocompleteField->attr('data-symfony--ux-autocomplete--autocomplete-url-value');
        $client->request('GET', $autocompleteUrl.'?query=Lovelace');
        self::assertResponseIsSuccessful();
        /** @var array{results: list<array{value: string, text: string}>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([['value' => $collaborateur->id(), 'text' => 'Ada Lovelace — manager@example.test']], $payload['results']);

        $crawler = $client->request('GET', '/referentiel/fiche/nouvelle');
        $form = $crawler->selectButton('Créer la fiche')->form();
        $form->disableValidation();
        $form->setValues([
            'fiche_creation[type]' => 'activite',
            'fiche_creation[label]' => 'Activité affiliée',
            'fiche_creation[modeIntervention]' => 'fixe',
            'fiche_creation[collaborateurExistant]' => $collaborateur->id(),
        ]);
        $client->submit($form);
        self::assertResponseRedirects();

        $affiliation = $this->connection->fetchAssociative('SELECT role FROM pim_fiche_affiliation');
        self::assertNotFalse($affiliation);
        self::assertSame(FicheAffiliationRole::Manager->value, $affiliation['role']);
        self::assertSame('fixe', $this->connection->fetchOne('SELECT mode_intervention FROM pim_activite'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_collaborateur'));
    }

    public function testWarnsOnDuplicateThenCreatesAfterConfirmation(): void
    {
        $client = $this->createClientWithUser();
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Château unique',
        ]);
        self::assertResponseRedirects();
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));

        // Même nom (casse différente) : avertissement, pas de création.
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'restaurant',
            'fiche_creation[label]' => 'château UNIQUE',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Doublons potentiels');
        self::assertSelectorTextContains('main', 'Château unique');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));

        // Confirmation explicite via le bouton « Créer quand même » : la création passe.
        $client->submitForm('Créer quand même', [
            'fiche_creation[type]' => 'restaurant',
            'fiche_creation[label]' => 'château UNIQUE',
        ]);
        self::assertResponseRedirects();
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }

    public function testCreatesANewCollaborateurWithPhoneAndQueuesAccessEmail(): void
    {
        $client = $this->createClientWithUser();
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'service_evenementiel',
            'fiche_creation[label]' => 'Service avec accès',
            'fiche_creation[collabPrenom]' => 'Grace',
            'fiche_creation[collabNom]' => 'Hopper',
            'fiche_creation[collabEmail]' => 'grace@example.test',
            'fiche_creation[collabTelephone]' => '+33 6 12 34 56 78',
            'fiche_creation[envoyerAcces]' => '1',
            'fiche_creation[trameEmail]' => 'Bonjour, voici vos accès.',
        ]);
        self::assertResponseRedirects();

        $collaborateur = $this->connection->fetchAssociative(
            'SELECT first_name, last_name, phone FROM pim_fiche_collaborateur WHERE email = ?',
            ['grace@example.test'],
        );
        self::assertNotFalse($collaborateur);
        self::assertSame('Grace', $collaborateur['first_name']);
        self::assertSame('+33 6 12 34 56 78', $collaborateur['phone']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_affiliation'));
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM outbox_message WHERE message_type LIKE '%CollaborateurAccessRequested'",
        ));
        /** @var array{emailBody: string} $body */
        $body = json_decode((string) $this->connection->fetchOne(
            "SELECT body FROM outbox_message WHERE message_type LIKE '%CollaborateurAccessRequested'",
        ), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Bonjour, voici vos accès.', $body['emailBody']);
    }
}
