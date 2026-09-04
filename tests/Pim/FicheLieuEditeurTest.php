<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Entity\FicheSalesforce;
use App\Etl\Service\MarketplaceClientInterface;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\StatutFiche;
use App\Tests\Etl\RecordingMarketplaceClient;
use App\Tests\Support\LieuComplet;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheLieuEditeurTest extends WebTestCase
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
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testLaSectionEnregistreSesChampsSansToucherLesAutres(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('editeur-fiche@example.test', ['ROLE_BP_EDITOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $lieu = new Lieu();
        $lieu->changeLabel('Château des sections');
        $lieu->changeGeneraleWebsiteUrl('https://exemple.test');
        $entityManager->persist($lieu);
        $entityManager->flush();
        $id = (string) $lieu->id();
        $client->loginUser($user);

        // Le rail des 16 sections maquette est là (dont « Booster ma visibilité » et
        // « Templates de message », maquette pure), la section 0 est active. L'éditeur
        // est la vue unique : suppression et médias y vivent aussi.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Château des sections');
        self::assertSame(16, $crawler->filter('nav[aria-label="Sections de la fiche"] li')->count());
        // Un bloc par titre maquette (relecture 2026-09-04) : cartes des onglets.
        $cartes = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] h2', $volet))->each(static fn (\Symfony\Component\DomCrawler\Crawler $h2): string => $h2->text(null, true));
        self::assertSame(['Informations générales', 'Disponibilités'], $cartes(0));
        self::assertSame(['Localisation', 'Accessibilité', 'Détails des accès PMR'], $cartes(1));
        self::assertSame(['Salles de réunion', 'Capacités'], $cartes(5));
        self::assertSame(['Séminaire à la journée', 'Séminaire avec nuitée', 'Location de salle seule', 'Cocktail et soirées', 'Restauration', 'Hébergement groupe', 'Offre spéciale'], $cartes(10));
        // Les questions « dispose de… » sont des Oui/Non qui ouvrent leur bloc (retour Clem).
        self::assertCount(2, $crawler->filter('input[type="radio"][name="lieu[hebergement][chambreHebergement]"]'));
        self::assertCount(6, $crawler->filter('#form-fiche section[data-volet="4"] [data-affichage-conditionnel-target="cible"][data-source="lieu_hebergement_chambreHebergement"]'));
        self::assertCount(7, $crawler->filter('#form-fiche section[data-volet="5"] [data-affichage-conditionnel-target="cible"][data-source="lieu_syntheseSalles_salleReunionExist"]'));
        self::assertCount(1, $crawler->filter('#form-fiche section[data-volet="5"][data-affichage-conditionnel-target="cible"][data-source="lieu_syntheseSalles_salleReunionExist"]'), 'La matrice des salles suit la question.');
        // Tout booléen est une question Oui / Non (seules les puces, les
        // interrupteurs des jours et la matrice des salles restent des cases).
        foreach (['lieu[disponibilites][dispoLieuPrivatisable]', 'lieu[informationsGenerales][generaleEtabRp]', 'lieu[accessibiliteDescription][pmrAcces]', 'lieu[rse][demarcheRse]', 'lieu[restauration][restaurantPrivatisable]', 'lieu[visibilite][afficherContact]', 'lieu[businessPremium]', 'lieu[syntheseSalles][salleReunionExist]'] as $champ) {
            self::assertCount(0, $crawler->filter(sprintf('input[type="checkbox"][name="%s"]', $champ)), $champ);
            self::assertCount(2, $crawler->filter(sprintf('input[type="radio"][name="%s"]', $champ)), $champ);
        }
        // Un éditeur ne voit pas la suppression (réservée aux validateurs).
        self::assertSelectorNotExists('.danger-form');

        // Section Médias : le gestionnaire de photos/documents du DAM est intégré ;
        // les pièces de facturation (URSSAF, RIB…) n'y sont plus proposées.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=11');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="lieu-media"]');
        self::assertStringNotContainsString('INFO_LEGALE_ATTESTATION_VIGILANCE_URSSAF', (string) $client->getResponse()->getContent());

        // Section Facturation & partenariat : cartes maquette + six dropzones dans l'onglet.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=13');
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Informations légales', 'Adresse de facturation', 'Contact de facturation', 'Mode de paiements acceptés', 'Conditions de paiement de l\'acompte', 'Conditions de paiement annulation', 'Paiement des soldes', 'Commission', 'Convention de partenariat', 'Conditions générales de ventes'],
            $crawler->filter('#form-fiche section[data-volet="13"] h2')->each(static fn (\Symfony\Component\DomCrawler\Crawler $h2): string => $h2->text(null, true)),
        );
        self::assertCount(6, $crawler->filter('#form-fiche section[data-volet="13"] input[type="file"]'));
        self::assertCount(1, $crawler->filter('select[name="lieu[administratif][modePaiementCarteListe][]"]'));
        self::assertCount(0, $crawler->filter('select[name="lieu[visibilite][modePaiementCarteListe][]"]'));

        // Section Salles : les collections gardent l'ajout/retrait Stimulus.
        $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=5');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="form-collection"]');

        // Soumission partielle de la section 0 : seul le label voyage,
        // le site web (hors requête) doit rester intact.
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $values['lieu']['label'] = 'Château renommé par section';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche enregistrée.');

        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame('Château renommé par section', $recharge->label());
        self::assertSame('https://exemple.test', $recharge->generaleWebsiteUrl());

        // Section Visibilité : la sélection des sites s'enregistre.
        $site = new SiteDiffusion('KACTUS_TEST', 'Kactus', 'Partenaires', false, false, 1, []);
        $entityManager->persist($site);
        $entityManager->flush();
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=14');
        self::assertResponseIsSuccessful();

        // Bloc Données Salesforce : message d'absence tant que la fiche est
        // inconnue de Salesforce, valeurs en lecture seule dès qu'une ligne
        // de refresh existe.
        self::assertSelectorTextContains('body', 'Fiche inconnue de Salesforce');
        $donnees = new FicheSalesforce($recharge->fiche()->id(), $recharge->fiche()->code());
        $donnees->mettreAJour(3.5, null, null, null, null, 4.2, 4.0, 'Redressement judiciaire', ['Compte A']);
        $entityManager->persist($donnees);
        $entityManager->flush();
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id.'?section=14');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Données Salesforce');
        self::assertSelectorTextContains('body', 'Note RSE globale');
        self::assertSelectorTextContains('body', '4.2');
        self::assertSelectorTextContains('body', 'Redressement judiciaire');
        self::assertSelectorTextContains('body', 'Compte A');

        $form = $crawler->selectButton('Enregistrer la diffusion')->form();
        $values = $form->getPhpValues();
        $values['sites_diffusion']['sites'] = [(string) $site->id()];
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();

        $entityManager->clear();
        $final = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $final);
        self::assertSame([$site->id()], $final->fiche()->siteDiffusionIds());
    }

    public function testLesChampsObligatoiresDeLaBiblePortentUnAsterisquePermanent(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        $client->loginUser($this->utilisateur($entityManager, 'asterisque@example.test', 'ROLE_BP_EDITOR'));
        $lieu = new Lieu();
        $lieu->changeLabel('Château des astérisques');
        $entityManager->persist($lieu);
        $entityManager->flush();

        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$lieu->id());
        self::assertResponseIsSuccessful();
        // Champs obligatoires de la bible : astérisque même sur un brouillon,
        // sans attribut HTML required (l'enregistrement partiel reste libre).
        self::assertStringContainsString('*', $crawler->filter('label[for="lieu_hebergement_chambreNbTotal"]')->text());
        self::assertStringContainsString('*', $crawler->filter('label[for="lieu_restauration_restaurantTotal"]')->text());
        self::assertStringContainsString('*', $crawler->filter('label[for="lieu_generaleTypologie"]')->text());
        self::assertStringNotContainsString('*', $crawler->filter('label[for="lieu_hebergement_chambreNbTotalTwin"]')->text());
        self::assertSame(0, $crawler->filter('#lieu_hebergement_chambreNbTotal[required]')->count());
        self::assertSame(0, $crawler->filter('#lieu_generaleTypologie[required]')->count());
        // La collection Accès porte la mention à sa place (pas de champ propre).
        self::assertStringContainsString('Accès *', implode(' | ', $crawler->filter('legend')->each(static fn ($legende): string => $legende->text())));
        self::assertSelectorTextContains('body', 'Au moins un accès de type aéroport et un accès de type gare');
    }

    public function testViderUnChampObligatoireDUneFichePublieeDemandeConfirmationAvantDeDepublier(): void
    {
        $client = self::createClient();
        // Le client marketplace de substitution doit survivre d'une requête
        // à l'autre (le kernel est sinon rebooté entre chaque requête).
        $client->disableReboot();
        self::getContainer()->set(MarketplaceClientInterface::class, new RecordingMarketplaceClient());
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        $client->loginUser($this->utilisateur($entityManager, 'validateur-depublication@example.test', 'ROLE_BP_VALIDATOR'));
        $lieu = $this->lieuPublie($entityManager, 'Château publié complet');
        $suivi = new FicheMarketplaceSync($lieu->fiche()->id(), $lieu->fiche()->code());
        $suivi->recordSynced('01JOLDSEQ');
        $entityManager->persist($suivi);
        $entityManager->flush();
        $id = (string) $lieu->id();

        // 1. Typologie retirée (clé absente du POST, comme un select vidé) :
        //    rien n'est enregistré, la page revient en 422 avec la modale.
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id);
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        unset($values['lieu']['generaleTypologie']);
        $crawler = $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-modal="depublication"]');
        self::assertSelectorTextContains('[data-modal="depublication"]', 'Cette fiche sera dépubliée');
        self::assertSelectorTextContains('[data-modal="depublication"]', 'Typologie');
        $oui = $crawler->filter('[data-modal="depublication"] button[type="submit"]');
        self::assertSame('lieu[confirmerDepublication]', $oui->attr('name'));
        self::assertSame('form-fiche', $oui->attr('form'));
        // Le formulaire re-rendu garde la saisie (typologie vide), sans transition fantôme.
        self::assertSame(0, $crawler->filter('#lieu_generaleTypologie option[selected]')->count());
        self::assertSelectorTextContains('body', 'Publiée');

        $entityManager->clear();
        $intact = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $intact);
        self::assertSame(StatutFiche::Publiee, $intact->fiche()->status());
        self::assertSame(['GENERALE_TYPOLOGIE_20'], $intact->generaleTypologie());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_message'));

        // 2. « Oui, dépublier » : même formulaire + submitter nommé.
        $values['lieu']['confirmerDepublication'] = '1';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche enregistrée.');
        self::assertSelectorTextContains('body', 'Fiche dépubliée : champs obligatoires vidés — Typologie.');

        $entityManager->clear();
        $depubliee = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $depubliee);
        self::assertSame(StatutFiche::EnCours, $depubliee->fiche()->status());
        self::assertSame([], $depubliee->generaleTypologie());
        self::assertStringContainsString('Typologie', (string) $depubliee->fiche()->validationFeedback());
        self::assertSame(1, $this->outboxCount('RemoveFicheFromMarketplace'));
        self::assertSame(1, $this->outboxCount('IndexFiche'));
    }

    public function testUnManquantPreexistantOuUneFicheEnCoursNeDemandentPasConfirmation(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        $client->loginUser($this->utilisateur($entityManager, 'validateur-preexistant@example.test', 'ROLE_BP_VALIDATOR'));

        // Fiche publiée déjà incomplète (cas des lieux legacy) : enregistrer
        // sans toucher aux obligatoires ne pose aucune question.
        $incomplet = $this->lieuPublie($entityManager, 'Château publié incomplet', static fn (Lieu $lieu) => $lieu->changeRestaurantTotal(null));
        $id = (string) $incomplet->id();
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id);
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $values['lieu']['label'] = 'Château publié incomplet renommé';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Fiche enregistrée.');
        self::assertSelectorNotExists('[data-modal="depublication"]');
        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame(StatutFiche::Publiee, $recharge->fiche()->status());
        self::assertSame('Château publié incomplet renommé', $recharge->label());

        // Fiche en cours : vider un obligatoire reste un brouillon libre.
        $brouillon = LieuComplet::completer(new Lieu());
        $brouillon->changeLabel('Brouillon complet');
        $entityManager->persist($brouillon);
        $entityManager->flush();
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$brouillon->id());
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        unset($values['lieu']['generaleTypologie']);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, (string) $brouillon->id());
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame([], $recharge->generaleTypologie());
        self::assertSame(StatutFiche::EnCours, $recharge->fiche()->status());
    }

    public function testUnEditeurQuiVideUnChampObligatoireDUneFichePublieeEstAussiPrevenu(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        $client->loginUser($this->utilisateur($entityManager, 'editeur-depublication@example.test', 'ROLE_BP_EDITOR'));
        $lieu = $this->lieuPublie($entityManager, 'Château publié éditeur');
        $id = (string) $lieu->id();

        // Description vidée : même modale que pour un validateur, et rien
        // n'est enregistré tant qu'il n'a pas confirmé (la fiche reste publiée).
        $crawler = $client->request('GET', '/referentiel/lieux/fiche/'.$id);
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $values['lieu']['accessibiliteDescription']['descGenerale'] = '';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-modal="depublication"]', 'Texte de description');
        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame(StatutFiche::Publiee, $recharge->fiche()->status());
        self::assertNotNull($recharge->descGenerale());

        $values['lieu']['confirmerDepublication'] = '1';
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $entityManager->clear();
        $recharge = $entityManager->find(Lieu::class, $id);
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame(StatutFiche::EnCours, $recharge->fiche()->status());
        self::assertNull($recharge->descGenerale());
        self::assertStringContainsString('Texte de description', (string) $recharge->fiche()->validationFeedback());
    }

    private function utilisateur(EntityManagerInterface $entityManager, string $email, string $role): User
    {
        $user = new User($email, [$role]);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function lieuPublie(EntityManagerInterface $entityManager, string $label, ?callable $avantPublication = null): Lieu
    {
        $lieu = LieuComplet::completer(new Lieu());
        $lieu->changeLabel($label);
        if (null !== $avantPublication) {
            $avantPublication($lieu);
        }
        $lieu->fiche()->publishForImport();
        $entityManager->persist($lieu);
        $entityManager->flush();

        return $lieu;
    }

    private function outboxCount(string $message): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type LIKE ?',
            ['%'.$message],
        );
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM etl_fiche_marketplace');
        $this->connection->executeStatement('DELETE FROM etl_fiche_salesforce');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_acces_lieu');
        $this->connection->executeStatement('DELETE FROM pim_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_fiche_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
