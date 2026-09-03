<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Account\Entity\User;
use App\Etl\Entity\FicheImportJob;
use App\Etl\Enum\ImportJobStatus;
use App\Etl\Message\ProcessFicheImportBatch;
use App\Etl\Message\StartFicheImport;
use App\Etl\MessageHandler\ProcessFicheImportBatchHandler;
use App\Etl\MessageHandler\StartFicheImportHandler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Lov\LieuLovCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Import en masse (Outils) : reprise d'un classeur au format de l'export du
 * référentiel — libellés LOV, une feuille par gamme, mode écrasement (le
 * fichier fait foi : un code met à jour en écrasant, pas de code crée).
 */
#[Group('database')]
final class ImportMasseTest extends WebTestCase
{
    private Connection $connection;
    private ?string $fichier = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->fichier && is_file($this->fichier)) {
            unlink($this->fichier);
        }
        if (isset($this->connection)) {
            foreach ([
                'etl_import_job_error', 'etl_import_job', 'pim_fiche_search', 'pim_fiche_attribute_value',
                'pim_fiche_site_diffusion', 'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu',
                'pim_ressource_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu',
                'pim_restaurant_salle', 'pim_restaurant_periode_fermeture', 'pim_restaurant_acces', 'pim_restaurant',
                'pim_fiche', 'pim_localisation', 'outbox_message', 'account_user',
            ] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }

        parent::tearDown();
    }

    public function testLeClasseurDExportEcraseMetAJourEtCree(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('import-masse@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        // Fiche existante : typologie + atout + une salle, que le fichier va écraser.
        $lieu = new Lieu();
        $lieu->changeLabel('Château avant import');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_1']);
        $lieu->changeAtout1('Ancien atout');
        $salle = new Salle();
        $salle->changeNom('Salle à supprimer');
        $lieu->addSalle($salle);
        $entityManager->persist($lieu);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot avant import');
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $codeLieu = $lieu->fiche()->code();
        $codeRestaurant = $restaurant->fiche()->code();
        $client->loginUser($user);

        // Classeur au format d'export : libellés LOV, une feuille par gamme.
        $typologie2 = LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')['GENERALE_TYPOLOGIE_2'];
        $this->fichier = sys_get_temp_dir().'/mdm-import-masse-'.uniqid().'.xlsx';
        $writer = new Writer();
        $writer->openToFile($this->fichier);
        $writer->getCurrentSheet()->setName('Lieux');
        $writer->addRow(Row::fromValues(['code', 'label', 'generale_typologie', 'atout1', 'atout2', 'salle_1_nom']));
        // Code présent : mise à jour écrasante — typologie par libellé,
        // atout1 vidé (cellule vide), salles remplacées par rien.
        $writer->addRow(Row::fromValues([$codeLieu, 'Château après import', $typologie2, '', 'Nouvel atout 2', '']));
        // Pas de code : création.
        $writer->addRow(Row::fromValues(['', 'Château créé en masse', $typologie2, 'Atout du créé', '', '']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Restaurants');
        $writer->addRow(Row::fromValues(['code', 'label']));
        $writer->addRow(Row::fromValues([$codeRestaurant, 'Bistrot après import']));
        $writer->addNewSheetAndMakeItCurrent()->setName('LOV');
        $writer->addRow(Row::fromValues(['Attribut', 'Code', 'Libellé']));
        $writer->close();

        // L'écran Outils → Import en masse accepte le classeur.
        $crawler = $client->request('GET', '/outils/import-masse');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Import en masse');
        $form = $crawler->selectButton('Lancer l\'import en masse')->form();
        $champFichier = $form['import_masse_upload[file]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\FileFormField::class, $champFichier);
        $champFichier->upload($this->fichier);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Import en masse lancé : 2 gamme(s) en file (Lieux, Restaurants).');

        // Un job par gamme, traité par les handlers.
        $jobs = $this->connection->fetchAllAssociative('SELECT id, type FROM etl_import_job ORDER BY type');
        self::assertCount(2, $jobs);

        $start = self::getContainer()->get(StartFicheImportHandler::class);
        $process = self::getContainer()->get(ProcessFicheImportBatchHandler::class);
        foreach ($jobs as $job) {
            $jobId = (string) Ulid::fromBinary((string) $job['id']);
            $start(new StartFicheImport($jobId));
            $process(new ProcessFicheImportBatch($jobId, 2));
        }
        foreach ($this->connection->fetchAllAssociative('SELECT status, failure_message, error_count FROM etl_import_job') as $etat) {
            self::assertSame(ImportJobStatus::Termine->value, (string) $etat['status'], (string) $etat['failure_message']);
            self::assertSame(0, (int) $etat['error_count']);
        }

        // Mise à jour écrasante : libellé LOV résolu, atout vidé, salles vidées.
        $entityManager->clear();
        $ficheLieu = $entityManager->getRepository(Fiche::class)->findOneBy(['code' => $codeLieu]);
        self::assertInstanceOf(Fiche::class, $ficheLieu);
        self::assertSame('Château après import', $ficheLieu->label());
        $lieuMaj = self::getContainer()->get(\App\Pim\Repository\LieuRepository::class)->findOneByFicheWithLocalisation($ficheLieu);
        self::assertInstanceOf(Lieu::class, $lieuMaj);
        self::assertSame(['GENERALE_TYPOLOGIE_2'], $lieuMaj->generaleTypologie());
        self::assertNull($lieuMaj->atout1());
        self::assertSame('Nouvel atout 2', $lieuMaj->atout2());
        self::assertCount(0, $lieuMaj->salles());

        // Création sans code, mise à jour du restaurant sur son autre feuille.
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM pim_fiche WHERE label = 'Château créé en masse'",
        ));
        $ficheRestaurant = $entityManager->getRepository(Fiche::class)->findOneBy(['code' => $codeRestaurant]);
        self::assertInstanceOf(Fiche::class, $ficheRestaurant);
        self::assertSame('Bistrot après import', $ficheRestaurant->label());

        // L'onglet porte son historique : un import par gamme, compteurs à jour.
        $crawler = $client->request('GET', '/outils/import-masse');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Historique des imports');
        $texte = $crawler->text(null, true);
        self::assertSame(2, substr_count($texte, basename($this->fichier)));
        self::assertStringContainsString('Lieux', $texte);
        self::assertStringContainsString('Restaurants', $texte);
        self::assertStringContainsString('Terminé', $texte);
        // Les cartes de synthèse des files (workers) sont sur la page.
        self::assertGreaterThan(0, $crawler->filter('turbo-frame#outils-indicateurs')->count());

        // Le détail d'un import (hors /admin) s'ouvre pour un validateur.
        $client->request('GET', '/outils/imports/'.(string) Ulid::fromBinary((string) $jobs[0]['id']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', basename((string) $this->fichier));
        self::assertSelectorTextContains('body', 'Retour à l\'import en masse');
    }

    public function testUnClasseurReduitAUneColonneNeToucheQueCetteColonne(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('import-masse-partiel@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château intact');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_1']);
        $lieu->changeAtout1('Atout conservé');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $codeLieu = $lieu->fiche()->code();
        $client->loginUser($user);

        // Classeur réduit à code + une seule colonne : sans même la colonne label.
        $this->fichier = sys_get_temp_dir().'/mdm-import-masse-partiel-'.uniqid().'.xlsx';
        $writer = new Writer();
        $writer->openToFile($this->fichier);
        $writer->getCurrentSheet()->setName('Lieux');
        $writer->addRow(Row::fromValues(['code', 'atout2']));
        $writer->addRow(Row::fromValues([$codeLieu, 'Seul champ modifié']));
        $writer->close();

        $crawler = $client->request('GET', '/outils/import-masse');
        $form = $crawler->selectButton('Lancer l\'import en masse')->form();
        $champFichier = $form['import_masse_upload[file]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\FileFormField::class, $champFichier);
        $champFichier->upload($this->fichier);
        $client->submit($form);
        self::assertResponseRedirects();

        $jobId = (string) Ulid::fromBinary((string) $this->connection->fetchOne('SELECT id FROM etl_import_job'));
        $start = self::getContainer()->get(StartFicheImportHandler::class);
        $process = self::getContainer()->get(ProcessFicheImportBatchHandler::class);
        $start(new StartFicheImport($jobId));
        $process(new ProcessFicheImportBatch($jobId, 2));
        $etat = $this->connection->fetchAssociative('SELECT status, failure_message FROM etl_import_job');
        self::assertIsArray($etat);
        self::assertSame(ImportJobStatus::Termine->value, (string) $etat['status'], (string) $etat['failure_message']);

        // Seule la colonne du fichier a bougé : le reste de la fiche est tel quel.
        $entityManager->clear();
        $fiche = $entityManager->getRepository(Fiche::class)->findOneBy(['code' => $codeLieu]);
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertSame('Château intact', $fiche->label());
        $lieuMaj = self::getContainer()->get(\App\Pim\Repository\LieuRepository::class)->findOneByFicheWithLocalisation($fiche);
        self::assertInstanceOf(Lieu::class, $lieuMaj);
        self::assertSame(['GENERALE_TYPOLOGIE_1'], $lieuMaj->generaleTypologie());
        self::assertSame('Atout conservé', $lieuMaj->atout1());
        self::assertSame('Seul champ modifié', $lieuMaj->atout2());
    }

    public function testDesColonnesInconnuesEchouentAvecSuggestions(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('import-masse-colonnes@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        // Deux en-têtes typo : chacun doit ressortir avec sa suggestion.
        $this->fichier = sys_get_temp_dir().'/mdm-import-masse-colonnes-'.uniqid().'.xlsx';
        $writer = new Writer();
        $writer->openToFile($this->fichier);
        $writer->getCurrentSheet()->setName('Lieux');
        $writer->addRow(Row::fromValues(['code', 'labell', 'atou1']));
        $writer->addRow(Row::fromValues(['', 'Château typo', 'Un atout']));
        $writer->close();

        $crawler = $client->request('GET', '/outils/import-masse');
        $form = $crawler->selectButton('Lancer l\'import en masse')->form();
        $champFichier = $form['import_masse_upload[file]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\FileFormField::class, $champFichier);
        $champFichier->upload($this->fichier);
        $client->submit($form);
        self::assertResponseRedirects();

        $jobId = (string) Ulid::fromBinary((string) $this->connection->fetchOne('SELECT id FROM etl_import_job'));
        $start = self::getContainer()->get(StartFicheImportHandler::class);
        $start(new StartFicheImport($jobId));
        $entityManager->flush();

        $etat = $this->connection->fetchAssociative('SELECT status, failure_message FROM etl_import_job');
        self::assertIsArray($etat);
        self::assertSame(ImportJobStatus::Echoue->value, (string) $etat['status']);
        $message = (string) $etat['failure_message'];
        self::assertStringContainsString('labell (proche de : label)', $message);
        self::assertStringContainsString('atou1 (proche de : atout1)', $message);
        self::assertStringContainsString('export du référentiel', $message);
        self::assertStringNotContainsString('modèle', $message);

        // La page détail porte le message complet.
        $client->request('GET', '/outils/imports/'.$jobId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'labell (proche de : label)');
    }

    public function testLaPageDetailResumeLesErreursGroupees(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('import-masse-resume@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $job = new FicheImportJob(\App\Pim\Enum\TypeFiche::Lieu, 'resume.xlsx', 'import-masse-resume@example.test');
        $entityManager->persist($job);
        // 7 occurrences du même couple colonne/message (aperçu tronqué à 5), 2 d'un autre.
        foreach ([4, 6, 9, 12, 15, 21, 30] as $ligne) {
            $entityManager->persist(new \App\Etl\Entity\FicheImportJobError($job, $ligne, 'generale_typologie', 'Code LOV inconnu pour GENERALE_TYPOLOGIE : « Pizzeria ».'));
        }
        foreach ([5, 8] as $ligne) {
            $entityManager->persist(new \App\Etl\Entity\FicheImportJobError($job, $ligne, 'atout1', 'Une autre erreur.'));
        }
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/outils/imports/'.$job->idString());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Résumé des erreurs');
        // Groupe majoritaire en premier, aperçu limité aux 5 premières lignes.
        // La carte « Résumé des erreurs » précède la carte « Erreurs ».
        $lignesResume = $crawler->filter('section.card.table-wrapper')->first()->filter('tbody tr');
        self::assertSame(2, $lignesResume->count());
        $premiere = $lignesResume->first()->text(null, true);
        self::assertStringContainsString('generale_typologie', $premiere);
        self::assertStringContainsString('7', $premiere);
        self::assertStringContainsString('4, 6, 9, 12, 15…', $premiere);
        self::assertStringContainsString('atout1', $lignesResume->last()->text(null, true));
    }

    public function testUnClasseurSansFeuilleDeGammeEstRefuse(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);

        $user = new User('import-masse-vide@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $this->fichier = sys_get_temp_dir().'/mdm-import-masse-vide-'.uniqid().'.xlsx';
        $writer = new Writer();
        $writer->openToFile($this->fichier);
        $writer->getCurrentSheet()->setName('Données');
        $writer->addRow(Row::fromValues(['code', 'label']));
        $writer->close();

        $crawler = $client->request('GET', '/outils/import-masse');
        $form = $crawler->selectButton('Lancer l\'import en masse')->form();
        $champFichier = $form['import_masse_upload[file]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\FileFormField::class, $champFichier);
        $champFichier->upload($this->fichier);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Aucune feuille de gamme reconnue');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM etl_import_job'));
    }
}
