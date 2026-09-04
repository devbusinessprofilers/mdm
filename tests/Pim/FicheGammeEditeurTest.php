<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Listes et éditeurs par gamme (Restaurant, Activité, Service) sur le patron du Lieu. */
#[Group('database')]
final class FicheGammeEditeurTest extends WebTestCase
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

    public function testListesEtEditeursDesTroisGammes(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('gammes@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot des gammes');
        $restaurant->changeSiteOfficiel('https://bistrot.test');
        $entityManager->persist($restaurant);
        $activite = new Activite();
        $activite->changeLabel('Escalade des gammes');
        $entityManager->persist($activite);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Traiteur événementiel des gammes');
        $entityManager->persist($service);
        // Assez d'activités pour déclencher la pagination (14 par page).
        for ($i = 1; $i <= 15; ++$i) {
            $autre = new Activite();
            $autre->changeLabel('Activité de pagination '.$i);
            $entityManager->persist($autre);
        }
        $entityManager->flush();
        $client->loginUser($user);

        // Les listes filtrées par gamme ne montrent que leur gamme.
        $client->request('GET', '/referentiel/restaurants');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Restaurants');
        self::assertSelectorTextContains('table', 'Bistrot des gammes');
        self::assertSelectorTextNotContains('table', 'Escalade des gammes');
        // La pagination d'une liste par gamme conserve le paramètre {gamme}.
        $crawler = $client->request('GET', '/referentiel/activites');
        self::assertResponseIsSuccessful();
        $suivante = $crawler->filter('a:contains("Page suivante")');
        self::assertGreaterThan(0, $suivante->count(), 'La pagination doit apparaître avec 16 activités.');
        self::assertStringStartsWith('/referentiel/activites', (string) $suivante->attr('href'));
        $client->request('GET', (string) $suivante->attr('href'));
        self::assertResponseIsSuccessful();
        $client->request('GET', '/referentiel/services');
        self::assertSelectorTextContains('table', 'Traiteur événementiel des gammes');

        // Éditeur Restaurant : rail de sections, soumission partielle du label
        // sans toucher au reste (le site officiel, hors requête, doit survivre).
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bistrot des gammes');
        self::assertStringStartsWith('RES-', $crawler->filter('.page-description')->first()->text(null, true));
        // L'éditeur est la vue unique : un validateur y voit la suppression.
        self::assertSelectorExists('.danger-form');
        $this->assertEditeurRestaurantIsoMaquette($crawler);
        // Section Médias : galerie au design de la fiche Lieu — photos de la
        // fiche et tuiles de documents, onglet interne « Menus » (maquette).
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id().'?section=7');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Photos de la fiche');
        self::assertSelectorTextContains('main', 'Documents');
        self::assertSelectorTextContains('main [role="tab"][data-media-tabs-onglet-param="plans"]', 'Menus');
        self::assertGreaterThan(0, $crawler->filter('[data-onglet="plans"] input[name="restaurant[menus][]"]')->count(), 'La dropzone Menus vit dans l\'onglet interne Menus.');
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id());
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $values['restaurant']['label'] = 'Bistrot renommé';
        // Soumission partielle stricte : seul le libellé voyage.
        unset($values['restaurant']['siteOfficiel'], $values['restaurant']['youtubeUrl']);
        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $entityManager->clear();
        $recharge = $entityManager->find(Restaurant::class, $restaurant->id());
        self::assertInstanceOf(Restaurant::class, $recharge);
        self::assertSame('Bistrot renommé', $recharge->label());
        self::assertSame('https://bistrot.test', $recharge->siteOfficiel());

        // Les éditeurs Activité et Service rendent avec leurs sections.
        $crawler = $client->request('GET', '/referentiel/activites/fiche/'.$activite->id());
        self::assertResponseIsSuccessful();
        $this->assertEditeurActiviteIsoMaquette($crawler);
        $crawler = $client->request('GET', '/referentiel/services/fiche/'.$service->id().'?section=1');
        self::assertResponseIsSuccessful();
        $this->assertEditeurServiceIsoMaquette($crawler);
    }

    // Le dépôt de supports commerciaux passe par le formulaire principal de la
    // fiche : la soumission partielle doit fusionner $request->files, sinon les
    // fichiers sont ignorés en silence (fiche « enregistrée », aucun document).
    public function testDepotSupportCommercialParLaSectionMedias(): void
    {
        $client = self::createClient();
        // Le stub de stockage doit survivre aux deux requêtes (GET puis POST) :
        // sans ça, le reboot du kernel entre les requêtes le remplace par le
        // vrai client S3, qui échoue en FilesystemException.
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        self::getContainer()->set(PrivateObjectStorageInterface::class, new class implements PrivateObjectStorageInterface {
            public function write(string $key, string $contents, array $options = []): void {}
            public function writeStream(string $key, mixed $stream, array $options = []): void {}
            public function read(string $key): string { return ''; }
            public function readStream(string $key): mixed { $stream = fopen('php://temp', 'r+b'); if (false === $stream) { throw new \RuntimeException('Flux temporaire indisponible.'); } return $stream; }
            public function exists(string $key): bool { return false; }
            public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string { return 'https://private.example.test/'.$key; }
            public function delete(string $key): void {}
            public function deleteDirectory(string $prefix): void {}
        });

        $user = new User('support@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Service à supports');
        $entityManager->persist($service);
        $entityManager->flush();
        $client->loginUser($user);

        $pdf = tempnam(sys_get_temp_dir(), 'mdm-support-');
        self::assertIsString($pdf);
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $crawler = $client->request('GET', '/referentiel/services/fiche/'.$service->id());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('button[form="form-fiche"]')->form();
        $values = $form->getPhpValues();
        $nom = (string) array_key_first($values);
        $values[$nom]['supportTitle'] = 'Plaquette 2026';
        $values[$nom]['supportSource'] = 'Prestataire';
        $fichiers = [$nom => ['supportsCommerciaux' => [
            new UploadedFile($pdf, 'plaquette.pdf', 'application/pdf', null, true),
        ]]];
        $client->request($form->getMethod(), $form->getUri(), $values, $fichiers);
        self::assertResponseRedirects();
        @unlink($pdf);

        $document = $this->connection->fetchAssociative(
            "SELECT nature, usage_code, legende, source FROM pim_ressource_lieu",
        );
        self::assertIsArray($document, 'Le support déposé doit créer une ressource document.');
        self::assertSame('document', $document['nature']);
        self::assertSame('PJ_SUPPORT_COMMERCIAUX', $document['usage_code']);
        self::assertSame('Plaquette 2026', $document['legende']);
        self::assertSame('Prestataire', $document['source']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dam_media_asset'));
    }

    /**
     * Onglets, cartes et champs du Restaurant dans l'ordre de la maquette
     * portail prestataire (Correction FRONT Champs, 2026-09).
     */
    private function assertEditeurRestaurantIsoMaquette(Crawler $crawler): void
    {
        $rail = $crawler->filter('nav[aria-label="Sections de la fiche"] li')->each(static fn (Crawler $li): string => trim(preg_replace('/\s*\d+ %$/', '', $li->text(null, true)) ?? ''));
        self::assertSame(
            ['Informations générales', 'Localisation & accessibilité', 'Description', 'Capacités', 'Services & équipements', 'RSE', 'Tarifs', 'Médias', 'Booster ma visibilité', 'Utilisateurs', 'Templates de message'],
            $rail,
        );
        self::assertStringNotContainsString('Classification', $crawler->filter('nav[aria-label="Sections de la fiche"]')->text());

        // Cartes de chaque volet, dans l'ordre (titres h2 des sections de formulaire).
        $cartes = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] h2', $volet))->each(static fn (Crawler $h2): string => $h2->text(null, true));
        self::assertSame(['Informations générales', 'Disponibilités'], $cartes(0));
        self::assertSame(['Localisation', 'Accessibilité', 'Détails des accès PMR'], $cartes(1));
        self::assertSame(['Description'], $cartes(2));
        self::assertSame(['Capacité assise (Groupe)', 'Capacité cocktail / debout', 'Salles & espaces privatisables'], $cartes(3));
        self::assertSame(['Tarifs'], $cartes(6));

        // Libellés dans l'ordre maquette ; les champs de Classification ouvrent la carte.
        $libelles = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] label', $volet))
            ->each(static fn (Crawler $l): string => preg_replace('/\s+/', ' ', $l->text(null, true)) ?? '');
        $infos = $libelles(0);
        self::assertSame('Nom du restaurant *', $infos[0]);
        self::assertContains('Typologie de restaurant *', $infos);
        self::assertContains("Type d'évènement *", $infos);
        self::assertLessThan(array_search('Site officiel *', $infos, true), array_search('Typologie de restaurant *', $infos, true));
        self::assertContains('Privatisation totale du restaurant', $infos);
        self::assertContains('Privatisation partielle (salon, étage, terrasse)', $infos);
        self::assertSame(
            ['Pays *', 'Rue *', 'Code postal *', 'Ville *', 'Arrondissement', 'Département *', 'Région *', 'Latitude *', 'Longitude *', 'Code pays ISO'],
            array_slice($libelles(1), 0, 10),
        );
        self::assertSame(['Description générale *', 'Les plus / atouts 1 *', 'Les plus / atouts 2 *', 'Les plus / atouts 3 *', 'Les plus / atouts 4 *', 'Les plus / atouts 5 *'], $libelles(2));
        // Les quatre capacités, autrefois rendues nulle part, sont saisissables.
        self::assertSame(
            ['Capacité assise maximum du restaurant (couverts) *', 'Capacité en salle privatisable ou espace clos *', 'Capacité banquet (repas assis festif) *', 'Capacité cocktail (maximum debout) *'],
            $libelles(3),
        );
        // Trois capacités assises + la capacité cocktail : quatre tiers de largeur.
        self::assertCount(4, $crawler->filter('#form-fiche section[data-volet="3"] .w-\\[calc\\(33\\.333\\%-16px\\)\\]'));

        // Oui / Non : radios, « Non » coché par défaut, plus de « Non renseigné ».
        foreach (['privatisationTotale', 'privatisationPartielle', 'accesPmr', 'toilettesPmr'] as $champ) {
            $radios = $crawler->filter(sprintf('input[type="radio"][name="restaurant[%s]"]', $champ));
            self::assertCount(2, $radios, $champ);
            self::assertSame(['1', '0'], $radios->each(static fn (Crawler $r): string => (string) $r->attr('value')));
            self::assertSame([false, true], $radios->each(static fn (Crawler $r): bool => ($n = $r->getNode(0)) instanceof \DOMElement && $n->hasAttribute('checked')), $champ);
        }
        self::assertStringNotContainsString('Non renseigné', $crawler->filter('#form-fiche')->text());
        // Toilettes PMR conditionnées par Accès PMR = Oui.
        $cible = $crawler->filter('[data-affichage-conditionnel-target="cible"][data-source="restaurant_accesPmr"]');
        self::assertCount(1, $cible);
        self::assertSame('1', $cible->attr('data-valeurs'));
        self::assertGreaterThan(0, $cible->filter('input[name="restaurant[toilettesPmr]"]')->count());

        // Onglet Tarifs : six lignes interrupteur + montant, conditionnées.
        $tarifs = $crawler->filter('#form-fiche section[data-volet="6"] [data-tarif]');
        self::assertSame(
            ['tarifDejeunerAssis', 'tarifCocktailDejeunatoire', 'tarifDinerAssis', 'tarifCocktailDinatoire', 'tarifForfaitVin', 'tarifForfaitAlcool'],
            $tarifs->each(static fn (Crawler $t): string => (string) $t->attr('data-tarif')),
        );
        self::assertSelectorTextContains('#form-fiche section[data-volet="6"]', 'Indiquez vos tarifs "à partir de"');
        self::assertSelectorTextContains('#form-fiche section[data-volet="6"]', 'Entrée + plat + dessert + eau + café');
        self::assertCount(6, $crawler->filter('#form-fiche section[data-volet="6"] [data-affichage-conditionnel-target="cible"][data-vider]'));
        self::assertCount(6, $crawler->filter('#form-fiche section[data-volet="6"] input[name^="restaurant[tarif"]'));
        // Accès par la route (ex-« Grande ville proche »).
        self::assertStringContainsString('Accès par la route', (string) $crawler->filter('#form-fiche section[data-volet="1"] [data-form-collection-prototype-value]')->first()->attr('data-form-collection-prototype-value'));
    }

    /** Onglets, cartes et champs de l'Activité dans l'ordre de la maquette. */
    private function assertEditeurActiviteIsoMaquette(Crawler $crawler): void
    {
        $rail = $crawler->filter('nav[aria-label="Sections de la fiche"] li')->each(static fn (Crawler $li): string => trim(preg_replace('/\s*\d+ %$/', '', $li->text(null, true)) ?? ''));
        self::assertSame(
            ['Informations générales', 'Localisation & accessibilité', 'Description', 'Capacités', 'RSE', 'Tarifs', 'Médias', 'Booster ma visibilité', 'Utilisateurs', 'Templates de message'],
            $rail,
        );
        $cartes = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] h2', $volet))->each(static fn (Crawler $h2): string => $h2->text(null, true));
        self::assertSame(['Informations générales'], $cartes(0));
        self::assertSame(['Rayon d\'action géographique', 'Localisation fixe', 'Localisation mobile'], $cartes(1));
        self::assertSame(['Description', 'Les plus'], $cartes(2));
        self::assertSame(['Capacité globale', 'Durée de l\'activité / Séminaire'], $cartes(3));
        self::assertSame(['Mes tarifs', 'Forfaits', 'Options'], $cartes(5));

        $libelles = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] label', $volet))
            ->each(static fn (Crawler $l): string => preg_replace('/\s+/', ' ', $l->text(null, true)) ?? '');
        $sansBruit = static fn (array $l): array => array_values(array_filter($l, static fn (string $t): bool => '' !== $t && !str_starts_with($t, 'Select ')));
        self::assertSame(
            ['Nom de l’activité *', 'Prestataire *', 'Langue parlée', "Thématique de l'activité *", 'Type *', 'Nautiques & Aquatiques', 'Créatives, Artistiques & Musicales', 'Culinaires & Œnologiques', 'Culturelles, Réflexions & Découvertes', 'Digital & High-Tech', 'Sensations fortes & Sports mécaniques', 'Sportives & Ludiques', 'Nature & RSE', 'Bien-être & Détente', 'Adhérent Business Premium', 'Partenaire BP'],
            $sansBruit($libelles(0)),
        );
        self::assertStringNotContainsString('Sous-thématiques —', $crawler->filter('#form-fiche')->text());
        // Sous-thématiques : une cible par thématique parente.
        $cibles = $crawler->filter('#form-fiche section[data-volet="0"] [data-affichage-conditionnel-target="cible"][data-source="activite_thematiques"]');
        self::assertCount(9, $cibles);
        self::assertSame('TA_NAUTIQUE_AQUATIQUE', $cibles->first()->attr('data-valeurs'));
        // Rayon d'action en radios, cartes fixe / mobile conditionnées.
        self::assertCount(2, $crawler->filter('input[type="radio"][name="activite[modeIntervention]"]'));
        $volets = $crawler->filter('#form-fiche section[data-volet="1"][data-affichage-conditionnel-target="cible"]');
        self::assertSame(['fixe', 'mobile'], $volets->each(static fn (Crawler $s): string => (string) $s->attr('data-valeurs')));
        self::assertSame(['Pays', 'Rue', 'Code postal', 'Ville *', 'Arrondissement', 'Département', 'Région', 'Latitude', 'Longitude', 'Code pays ISO', 'Pays', 'Région(s) *', 'Département(s)', 'Toute la France'], array_slice($sansBruit($libelles(1)), 3));
        self::assertSame(['Description générale *', 'Ce que comprend la prestation', 'Objectifs de séminaire *', 'Plus n°1', 'Plus n°2', 'Plus n°3', 'Plus n°4', 'Plus n°5'], $sansBruit($libelles(2)));
        self::assertSame(['Nombre de participants minimum *', 'Nombre de participants maximum *', 'Temps minimum *', 'Temps maximum *'], $sansBruit($libelles(3)));
        self::assertCount(2, $crawler->filter('#form-fiche section[data-volet="3"] input[type="time"]'));
        // Forfaits / options : trois emplacements par type, champs désactivés tant que non cochés.
        self::assertSame('Tarifs à partir de (/pers)', $sansBruit($libelles(5))[0]);
        foreach (['forfait', 'option'] as $type) {
            self::assertCount(3, $crawler->filter(sprintf('[data-offre^="%s-"]', $type)));
            self::assertCount(3, $crawler->filter(sprintf('input[name^="activite[offres][nouveau_%s_"][name$="[type]"][disabled]', $type)));
            self::assertCount(0, $crawler->filter(sprintf('input[name="offre_active[%s][0]"][checked]', $type)));
        }
        self::assertSame('2', $crawler->filter('input[name="activite[offres][nouveau_option_2][position]"]')->attr('value'));
        self::assertSame('hidden', $crawler->filter('input[name="activite[offres][nouveau_option_2][position]"]')->attr('type'));
        self::assertStringContainsString('Nom option', $crawler->filter('[data-offre="option-0"]')->text());
    }

    /** Onglets, cartes et champs du Service événementiel dans l'ordre de la maquette. */
    private function assertEditeurServiceIsoMaquette(Crawler $crawler): void
    {
        $rail = $crawler->filter('nav[aria-label="Sections de la fiche"] li')->each(static fn (Crawler $li): string => trim(preg_replace('/\s*\d+ %$/', '', $li->text(null, true)) ?? ''));
        self::assertSame(
            ['Informations générales', 'Localisation & accessibilité', 'Prestations', 'Tarifs', 'Médias', 'Booster ma visibilité', 'Utilisateurs', 'Templates de message'],
            $rail,
        );
        $cartes = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] h2', $volet))->each(static fn (Crawler $h2): string => $h2->text(null, true));
        self::assertSame(['Informations générales', 'Description générale', 'Prestations', 'Matériel'], $cartes(0));
        self::assertSame(['Localisation', 'Zone d\'intervention principale', 'Accessibilité', 'Détails des accès PMR'], $cartes(1));
        self::assertSame(['Prestations'], $cartes(2));
        self::assertSame(['Tarifs'], $cartes(3));

        $libelles = static fn (int $volet): array => $crawler->filter(sprintf('#form-fiche section[data-volet="%d"] label', $volet))
            ->each(static fn (Crawler $l): string => preg_replace('/\s+/', ' ', $l->text(null, true)) ?? '');
        $sansRadios = static fn (array $l): array => array_values(array_filter($l, static fn (string $t): bool => !in_array($t, ['Oui', 'Non', ''], true) && !str_starts_with($t, 'Select ')));
        $infos = $sansRadios($libelles(0));
        self::assertSame('Nom du prestataire *', $infos[0]);
        self::assertSame(['Êtes-vous un prestataire ESAT ?', 'Êtes-vous un prestataire RSE ?'], array_slice($infos, 1, 2));
        self::assertContains('Description générale *', $infos);
        self::assertContains('Votre prestation est-elle adaptée aux femmes enceintes ?', $infos);
        self::assertContains('Y a-t-il des contraintes logistiques ?', $infos);
        self::assertLessThan(array_search('Y a-t-il des équipements à prévoir par les participants ?', $infos, true), array_search('Y a-t-il des contraintes logistiques ?', $infos, true));
        $localisation = $sansRadios($libelles(1));
        self::assertSame(['Rayon d’action *', 'Pays *', 'Rue *', 'Code postal *', 'Ville *', 'Arrondissement *', 'Département *', 'Région *', 'Latitude *', 'Longitude *', 'Code pays ISO', 'Région(s) *', 'Département(s) *', 'Pays *'], array_slice($localisation, 0, 14));
        self::assertContains('Matériel ou prestation adaptée aux publics PMR', $localisation);
        $prestations = $sansRadios($libelles(2));
        self::assertSame(['Prestations *', 'Accueil et sécurité', 'Cadeaux clients & Goodies', 'Communication & Publicité', 'Technique & Audiovisuel', 'Son & Vidéo', 'Animations & Artistes', 'Traduction & Interprétariat', 'Transports & Logistique', 'Traiteurs', 'Divers & Sur-mesure', 'Digital & Hybride'], $prestations);
        self::assertSame(['Par prestation *', 'Par personne *', 'Par jour *', 'Par demi journée *', 'Par heure *', 'Sur devis'], $sansRadios($libelles(3)));
        self::assertStringNotContainsString('Sous-prestations —', $crawler->filter('#form-fiche')->text());

        // Oui/Non en radios « Non » par défaut ; matériel PMR conditionné à Accès PMR.
        foreach (['prestataireEsat', 'demarcheRse', 'surDevis', 'accesPmr', 'materielAdaptePmr'] as $champ) {
            $radios = $crawler->filter(sprintf('input[type="radio"][name="service_evenementiel[%s]"]', $champ));
            self::assertCount(2, $radios, $champ);
            self::assertSame([false, true], $radios->each(static fn (Crawler $r): bool => ($n = $r->getNode(0)) instanceof \DOMElement && $n->hasAttribute('checked')), $champ);
        }
        $cible = $crawler->filter('[data-affichage-conditionnel-target="cible"][data-source="service_evenementiel_accesPmr"]');
        self::assertCount(1, $cible);
        self::assertGreaterThan(0, $cible->filter('input[name="service_evenementiel[materielAdaptePmr]"]')->count());
        // Collection d'accès : quatre types maquette, bouton de suggestion.
        $prototype = (string) $crawler->filter('#form-fiche section[data-volet="1"] [data-form-collection-prototype-value]')->first()->attr('data-form-collection-prototype-value');
        foreach (['Accès par la route', 'Parking(s)', 'Gare(s)', 'Aéroport(s)'] as $type) {
            self::assertStringContainsString($type, $prototype);
        }
        self::assertStringNotContainsString('Métro', $prototype);
        self::assertStringContainsString('Suggérer les accès', implode(' ', $crawler->filter('#form-fiche section[data-volet="1"]')->each(static fn (Crawler $s): string => $s->text(null, true))));
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM dam_media_duplicate_alert');
        $this->connection->executeStatement('DELETE FROM dam_media_rendition');
        $this->connection->executeStatement('DELETE FROM dam_media_phash_band');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_salle');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_periode_fermeture');
        $this->connection->executeStatement('DELETE FROM pim_restaurant_acces');
        $this->connection->executeStatement('DELETE FROM pim_service_acces');
        $this->connection->executeStatement('DELETE FROM pim_activite_offre');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM account_user');
    }
}
