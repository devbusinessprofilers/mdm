<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\FicheCollaborateur;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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
        // Codes postaux distincts : une adresse identique serait signalée en doublon.
        $expectations = [
            ['lieu', 'pim_lieu', '/referentiel/lieux/fiche/', '75001'],
            ['restaurant', 'pim_restaurant', '/referentiel/restaurants/fiche/', '75002'],
            ['activite', 'pim_activite', '/referentiel/activites/fiche/', '75003'],
            ['service_evenementiel', 'pim_service_evenementiel', '/referentiel/services/fiche/', '75004'],
        ];
        foreach ($expectations as [$type, $table, $editPrefix, $codePostal]) {
            $client->request('GET', '/referentiel/fiche/nouvelle?type='.$type);
            self::assertResponseIsSuccessful();
            $client->submitForm('Créer la fiche', [
                'fiche_creation[type]' => $type,
                'fiche_creation[label]' => 'Fiche '.$type,
                'fiche_creation[localisation][codePostal]' => $codePostal,
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

    public function testRejectsCreationWithoutCodePostal(): void
    {
        $client = $this->createClientWithUser();
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Sans code postal',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Le code postal est obligatoire.');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));
    }

    public function testLaPageDeCreationPorteLaRechercheDAdresse(): void
    {
        $client = $this->createClientWithUser();
        $client->request('GET', '/referentiel/fiche/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="adresse-autocomplete"]');
        // Le sélecteur de pays démarre sur la France et reste hors soumission.
        self::assertSelectorExists('select[name="adresse-recherche-pays"][form="hors-soumission"] option[value="FR"][selected]');
        // type="button" : « Appliquer le choix » ne doit jamais soumettre la création.
        self::assertSelectorExists('button[type="button"][data-action="adresse-autocomplete#appliquer"]');
    }

    public function testLAutocompletionDAdresseRepondUneListeVideSansCleGeoapify(): void
    {
        $client = $this->createClientWithUser();
        // Hors de France, seul Geoapify répond ; en test sa clé est vide :
        // client désactivé, aucun appel réseau.
        $client->request('GET', '/referentiel/fiche/adresse-autocomplete', ['nom' => 'Château de Chantilly', 'q' => 'Chantilly', 'pays' => 'be']);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['suggestions' => []],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testLAutocompletionDAdresseSertLAnnuaireDesEntreprisesEnFrance(): void
    {
        $client = $this->createClientWithUser();
        // Sans cela le kernel reboote entre les requêtes et la réponse simulée est perdue.
        $client->disableReboot();
        $mock = self::getContainer()->get('test.recherche_entreprises.mock_http_client');
        self::assertInstanceOf(MockHttpClient::class, $mock);
        $mock->setResponseFactory([new MockResponse((string) json_encode(['results' => [[
            'nom_complet' => 'BUSINESS PROFILERS',
            'siege' => [
                'adresse' => '1 AVENUE DU GENERAL DE GAULLE 60500 CHANTILLY',
                'numero_voie' => '1',
                'type_voie' => 'AVENUE',
                'libelle_voie' => 'DU GENERAL DE GAULLE',
                'code_postal' => '60500',
                'libelle_commune' => 'CHANTILLY',
                'departement' => '60',
                'latitude' => '49.1974',
                'longitude' => '2.4623',
            ],
        ]]], JSON_THROW_ON_ERROR))]);

        $client->request('GET', '/referentiel/fiche/adresse-autocomplete', ['nom' => 'Business Profilers', 'q' => '', 'pays' => 'fr']);

        self::assertResponseIsSuccessful();
        $donnees = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($donnees);
        self::assertSame('BUSINESS PROFILERS — 1 Avenue du General de Gaulle 60500 Chantilly', $donnees['suggestions'][0]['label'] ?? null);
        self::assertSame('1 Avenue du General de Gaulle', $donnees['suggestions'][0]['ruePostale'] ?? null);
        self::assertSame('Oise', $donnees['suggestions'][0]['departement'] ?? null);
        self::assertSame('FR', $donnees['suggestions'][0]['countryCode'] ?? null);
    }

    public function testPrefillsFacturationEtPartenariatFromAnnuaire(): void
    {
        $client = $this->createClientWithUser();
        // Sans cela le kernel reboote entre les requêtes et la réponse simulée est perdue.
        $client->disableReboot();
        $mock = self::getContainer()->get('test.recherche_entreprises.mock_http_client');
        self::assertInstanceOf(MockHttpClient::class, $mock);
        $mock->setResponseFactory([new MockResponse(json_encode(['results' => [[
            'nom_complet' => 'CHATEAU DES TESTS (CDT)',
            'nom_raison_sociale' => 'CHATEAU DES TESTS',
            'siren' => '480674100',
            'dirigeants' => [
                ['type_de_dirigeant' => 'personne physique', 'nom' => 'DURAND', 'prenoms' => 'JEAN, MARIE', 'qualite' => 'Président'],
            ],
            'siege' => [
                'siret' => '48067410000031',
                'numero_voie' => '1',
                'type_voie' => 'AVENUE',
                'libelle_voie' => 'DU GENERAL DE GAULLE',
                'code_postal' => '60500',
                'libelle_commune' => 'CHANTILLY',
                'latitude' => '49.19',
                'longitude' => '2.46',
            ],
        ]]], JSON_THROW_ON_ERROR))]);

        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Château des tests',
            'fiche_creation[localisation][codePostal]' => '60500',
            'fiche_creation[localisation][ville]' => 'Chantilly',
        ]);
        self::assertResponseRedirects();

        $administratif = $this->connection->fetchAssociative('SELECT * FROM pim_lieu_administratif');
        self::assertNotFalse($administratif);
        self::assertSame('CHATEAU DES TESTS', $administratif['info_legale_nom']);
        self::assertSame('1 AVENUE DU GENERAL DE GAULLE', $administratif['info_legale_rue_postal']);
        self::assertSame('60500', $administratif['info_legale_code_postal']);
        self::assertSame('CHANTILLY', $administratif['info_legale_ville']);
        self::assertSame('France', $administratif['infor_legale_pays']);
        self::assertSame('48067410000031', $administratif['info_legale_siret']);
        self::assertSame('FR39480674100', $administratif['info_legale_num_tva']);
        self::assertSame('CHATEAU DES TESTS', $administratif['adresse_facturation_nom']);
        self::assertSame('1 AVENUE DU GENERAL DE GAULLE', $administratif['adresse_facturation_rue_postal']);
        self::assertSame('60500', $administratif['adresse_facturation_code_postal']);
        self::assertSame('CHANTILLY', $administratif['adresse_facturation_ville']);
        self::assertSame('France', $administratif['adresse_facturation_pays']);
        self::assertSame('FR39480674100', $administratif['adresse_facturation_num_tva']);
        self::assertSame('JEAN', $administratif['signataire_prenom']);
        self::assertSame('DURAND', $administratif['signataire_nom']);
        // La saisie utilisateur reste prioritaire sur l'annuaire.
        self::assertSame('Chantilly', $this->connection->fetchOne('SELECT ville FROM pim_localisation'));
    }

    public function testLieuCreationStoresLocalisationReferencementAndVisibilite(): void
    {
        $client = $this->createClientWithUser();
        $crawler = $client->request('GET', '/referentiel/fiche/nouvelle');
        // Les typologies sont des cases à cocher (puces maquette) : DomCrawler
        // ne fait pas de correspondance par valeur sur un groupe `name[]`,
        // la validation client est donc débrayée.
        $form = $crawler->selectButton('Créer la fiche')->form();
        $form->disableValidation();
        $form->setValues([
            'fiche_creation[type]' => 'lieu',
            'fiche_creation[label]' => 'Château des tests',
            'fiche_creation[localisation][pays]' => 'France',
            'fiche_creation[localisation][codePostal]' => '60500',
            'fiche_creation[localisation][ville]' => 'Chantilly',
            'fiche_creation[lieuTypologie]' => ['GENERALE_TYPOLOGIE_6'],
            'fiche_creation[businessPremium]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();

        self::assertSame('Chantilly', $this->connection->fetchOne('SELECT ville FROM pim_localisation'));
        // Le niveau de statut (MICE_STATUT) ne se choisit plus à la création,
        // seul l'interrupteur Business Premium y figure.
        self::assertNull($this->connection->fetchOne('SELECT mice_statut FROM pim_lieu'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT business_premium FROM pim_fiche'));
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
        $crawler = $client->request('GET', '/referentiel/fiche/nouvelle');
        // Cases à cocher `name[]` : pas de correspondance par valeur côté
        // DomCrawler, validation client débrayée (cf. test précédent).
        $form = $crawler->selectButton('Créer la fiche')->form();
        $form->disableValidation();
        $form->setValues([
            'fiche_creation[type]' => 'restaurant',
            'fiche_creation[label]' => 'Restaurant strict',
            'fiche_creation[localisation][codePostal]' => '75001',
            'fiche_creation[lieuTypologie]' => ['GENERALE_TYPOLOGIE_6'],
            'fiche_creation[modeIntervention]' => 'mobile',
        ]);
        $client->submit($form);
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
            'fiche_creation[localisation][codePostal]' => '75001',
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
            'fiche_creation[localisation][codePostal]' => '75001',
        ]);
        self::assertResponseRedirects();
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));

        // Même nom (casse différente) : avertissement, pas de création.
        $client->request('GET', '/referentiel/fiche/nouvelle');
        $client->submitForm('Créer la fiche', [
            'fiche_creation[type]' => 'restaurant',
            'fiche_creation[label]' => 'château UNIQUE',
            'fiche_creation[localisation][codePostal]' => '75001',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Doublons potentiels');
        self::assertSelectorTextContains('main', 'Château unique');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche'));

        // Confirmation explicite via le bouton « Créer quand même » : la création passe.
        $client->submitForm('Créer quand même', [
            'fiche_creation[type]' => 'restaurant',
            'fiche_creation[label]' => 'château UNIQUE',
            'fiche_creation[localisation][codePostal]' => '75001',
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
            'fiche_creation[localisation][codePostal]' => '75001',
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
