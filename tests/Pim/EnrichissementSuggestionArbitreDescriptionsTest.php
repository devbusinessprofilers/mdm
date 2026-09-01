<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\FicheSuggestion;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\FicheSuggestionRepository;
use App\Pim\Service\EnrichissementSuggestionArbitre;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Pim\Service\SuggestionProposee;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Accepter une suggestion de description (source IA) applique le texte complet
 * du payload sur les gammes Restaurant et Service — les deux branches ajoutées
 * pour le bouton « Enrichir ce qui manque ».
 */
#[Group('database')]
final class EnrichissementSuggestionArbitreDescriptionsTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testAccepterAppliqueLaDescriptionProposeeAuRestaurant(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Table des arbitrages');
        $em->persist($restaurant);
        $em->flush();

        $this->accepterDescription($restaurant->fiche(), 'restaurant_desc_generale', 'Cuisine de saison au cœur de la vieille ville.');

        $em->clear();
        $recharge = $em->find(Restaurant::class, $restaurant->id());
        self::assertInstanceOf(Restaurant::class, $recharge);
        self::assertSame('Cuisine de saison au cœur de la vieille ville.', $recharge->descriptionGenerale());
    }

    public function testAccepterAppliqueLaDescriptionProposeeAuService(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $service = new ServiceEvenementiel();
        $service->changeLabel('Traiteur des arbitrages');
        $em->persist($service);
        $em->flush();

        $this->accepterDescription($service->fiche(), 'service_desc_generale', 'Traiteur événementiel, formules cocktail et dîner assis.');

        $em->clear();
        $recharge = $em->find(ServiceEvenementiel::class, $service->id());
        self::assertInstanceOf(ServiceEvenementiel::class, $recharge);
        self::assertSame('Traiteur événementiel, formules cocktail et dîner assis.', $recharge->descriptionGenerale());
    }

    public function testAccepterUneChaineRemplitAussiLeSelecteurLov(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Best Western des arbitrages');
        $em->persist($lieu);
        $suggestion = new FicheSuggestion(
            $lieu->fiche(),
            SuggestionSource::Wikidata,
            SuggestionAction::RemplirChamp,
            'lieu_chaine',
            'Chaîne / groupe hôtelier',
            null,
            'Best Western',
        );
        $em->persist($suggestion);
        $em->flush();

        self::getContainer()->get(EnrichissementSuggestionArbitre::class)->accepter($suggestion, 'testeur');

        $em->clear();
        $recharge = $em->find(Lieu::class, $lieu->id());
        self::assertInstanceOf(Lieu::class, $recharge);
        // Le libellé correspond à une entrée de la liste : réutilisée telle
        // quelle dans le sélecteur « Groupe et chaîne hôtelière » (champ unique).
        self::assertContains('GENERALE_CHAINES_GROUPE_HOT_7', $recharge->generaleChainesGroupeHot());
    }

    public function testAccepterUneChaineHorsListeCreeLaValeurLov(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('NH Collection des arbitrages');
        $em->persist($lieu);
        $suggestion = new FicheSuggestion(
            $lieu->fiche(),
            SuggestionSource::Wikidata,
            SuggestionAction::RemplirChamp,
            'lieu_chaine',
            'Chaîne / groupe hôtelier',
            null,
            'NH Hotel Group',
        );
        $em->persist($suggestion);
        $em->flush();

        self::getContainer()->get(EnrichissementSuggestionArbitre::class)->accepter($suggestion, 'testeur');

        $em->clear();
        $recharge = $em->find(Lieu::class, $lieu->id());
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertContains('GENERALE_CHAINES_GROUPE_HOT_NH_HOTEL_GROUP', $recharge->generaleChainesGroupeHot());
        // La valeur LOV a été créée et active (la resynchro marketplace du
        // dictionnaire, gardée par isConfigured, est couverte par
        // SyncLovDictionaryHandlerTest).
        $valeur = $this->connection->fetchAssociative(
            "SELECT label, active FROM pim_attribute_value WHERE code = 'GENERALE_CHAINES_GROUPE_HOT_NH_HOTEL_GROUP'",
        );
        self::assertIsArray($valeur);
        self::assertSame('NH Hotel Group', $valeur['label']);
        self::assertSame(1, (int) $valeur['active']);
    }

    public function testAccepterLesNouveauxChampsGeoapifyEtSirene(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $arbitre = self::getContainer()->get(EnrichissementSuggestionArbitre::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel enrichi de partout');
        $em->persist($lieu);
        $suggestions = [
            ['lieu_website', 'Site web', 'https://hotel.example', null],
            ['lieu_lov_typologie', 'Typologie', 'Hôtel 4 étoiles', ['attribut' => 'GENERALE_TYPOLOGIE', 'codes' => ['GENERALE_TYPOLOGIE_3']]],
            ['info_legale_forme_juridique', 'Forme juridique', 'Société par actions simplifiée (SAS)', null],
        ];
        $entites = [];
        foreach ($suggestions as [$champ, $label, $valeur, $payload]) {
            $entites[] = $s = new FicheSuggestion($lieu->fiche(), SuggestionSource::Geoapify, SuggestionAction::RemplirChamp, $champ, $label, null, $valeur, null, $payload);
            $em->persist($s);
        }
        $em->flush();

        foreach ($entites as $suggestion) {
            $arbitre->accepter($suggestion, 'testeur');
        }

        $em->clear();
        $recharge = $em->find(Lieu::class, $lieu->id());
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertSame('https://hotel.example', $recharge->generaleWebsiteUrl());
        self::assertContains('GENERALE_TYPOLOGIE_3', $recharge->generaleTypologie());
        self::assertSame('Société par actions simplifiée (SAS)', $recharge->administratif()->infoLegaleFormeJuridique());
    }

    public function testAccepterLaCasePmrEtLeNombreDeChambres(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $arbitre = self::getContainer()->get(EnrichissementSuggestionArbitre::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel accessible');
        $em->persist($lieu);
        $pmr = new FicheSuggestion($lieu->fiche(), SuggestionSource::Geoapify, SuggestionAction::RemplirChamp, 'lieu_pmr_acces', 'Accès PMR', null, 'Oui', null, ['bool' => true]);
        $chambres = new FicheSuggestion($lieu->fiche(), SuggestionSource::Geoapify, SuggestionAction::RemplirChamp, 'lieu_chambre_nb_total', 'Nombre total de chambres', null, '45', null, ['int' => 45]);
        $em->persist($pmr);
        $em->persist($chambres);
        $em->flush();

        $arbitre->accepter($pmr, 'testeur');
        $arbitre->accepter($chambres, 'testeur');

        $em->clear();
        $recharge = $em->find(Lieu::class, $lieu->id());
        self::assertInstanceOf(Lieu::class, $recharge);
        self::assertTrue($recharge->pmrAcces());
        self::assertSame(45, $recharge->chambreNbTotal());
    }

    public function testUneSuggestionDeChambresSaisiesDepuisLeScanEstPerimee(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Hôtel déjà compté');
        $lieu->changeChambreNbTotal(30);
        $em->persist($lieu);
        $suggestion = new FicheSuggestion($lieu->fiche(), SuggestionSource::Geoapify, SuggestionAction::RemplirChamp, 'lieu_chambre_nb_total', 'Nombre total de chambres', null, '45', null, ['int' => 45]);
        $em->persist($suggestion);
        $em->flush();

        $this->expectException(\DomainException::class);
        self::getContainer()->get(EnrichissementSuggestionArbitre::class)->accepter($suggestion, 'testeur');
    }

    public function testAccepterUnCodeDUnAutreSchemaCocheLaBonneCaseDuReferentiel(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        // Catalogue runtime chargé comme au réel (requête web / commande) :
        // TYPE_CUISINE y est codé TYPE_CUISINE_N, pas en mnémoniques.
        self::getContainer()->get(\App\Pim\Lov\LovRuntimeCatalog::class)->reload();
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Brasserie des référentiels');
        $em->persist($restaurant);
        $suggestion = new FicheSuggestion(
            $restaurant->fiche(),
            SuggestionSource::Geoapify,
            SuggestionAction::RemplirChamp,
            'restaurant_types_cuisine',
            'Type de cuisine',
            null,
            'Fruits de mer',
            null,
            ['codes' => ['FRUITS_DE_MER']],
        );
        $em->persist($suggestion);
        $em->flush();

        self::getContainer()->get(EnrichissementSuggestionArbitre::class)->accepter($suggestion, 'testeur');

        $em->clear();
        $recharge = $em->find(Restaurant::class, $restaurant->id());
        self::assertInstanceOf(Restaurant::class, $recharge);
        // « FRUITS_DE_MER » (ancien schéma) résolu par libellé vers le code
        // effectif du référentiel : la case « Fruits de mer » est cochée.
        self::assertContains('TYPE_CUISINE_44', $recharge->typesCuisine());
    }

    private function accepterDescription(\App\Pim\Entity\Fiche $fiche, string $champ, string $texte): void
    {
        $enregistreur = self::getContainer()->get(FicheSuggestionEnregistreur::class);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $enregistreur->enregistrer($fiche, SuggestionSource::Ia, [new SuggestionProposee(
            SuggestionAction::RemplirChamp,
            $champ,
            'Description générale',
            null,
            mb_substr($texte, 0, 200),
            null,
            ['text' => $texte],
        )]);
        $em->flush();
        $suggestion = self::getContainer()->get(FicheSuggestionRepository::class)->findPourCle($fiche, SuggestionSource::Ia, $champ);
        self::assertNotNull($suggestion);
        self::getContainer()->get(EnrichissementSuggestionArbitre::class)->accepter($suggestion, 'testeur');
    }

    private function clear(): void
    {
        // Valeur LOV créée à la volée par l'accept (traductions en cascade).
        $this->connection->executeStatement("DELETE FROM pim_attribute_value WHERE code = 'GENERALE_CHAINES_GROUPE_HOT_NH_HOTEL_GROUP'");
        foreach ([
            'outbox_message',
            'pim_fiche_suggestion',
            'pim_fiche_search',
            'pim_fiche_attribute_value',
            'pim_restaurant',
            'pim_service_evenementiel',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
