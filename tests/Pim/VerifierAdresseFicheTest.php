<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Dashboard\Repository\QualiteRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\MessageHandler\IndexFicheHandler;
use App\Pim\MessageHandler\VerifierAdresseFicheHandler;
use App\Pim\Service\BanClientInterface;
use App\Tests\Support\CommeUnWorker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class VerifierAdresseFicheTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
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

    public function testLaModificationDAdresseDeclencheLaVerificationEtLEnrichissement(): void
    {
        $lieu = $this->lieuFrancais();
        $code = (string) $lieu->fiche()->code();
        self::getContainer()->set(BanClientInterface::class, new FakeBanClient([
            $code => [
                'score' => 0.93,
                'label' => "24 Rue des Tests 49590 Fontevraud-l'Abbaye",
                'codePostal' => '49590',
                'ville' => "Fontevraud-l'Abbaye",
                'latitude' => '47.18',
                'longitude' => '0.05',
                'type' => 'housenumber',
            ],
        ]));

        // Toute mutation converge vers IndexFiche : l'empreinte diffère du
        // dernier passage BAN (jamais vérifié) → vérification enfilée.
        $indexHandler = self::getContainer()->get(IndexFicheHandler::class);
        $indexHandler(new IndexFiche($lieu->fiche()->idString()));
        self::assertSame(1, $this->outboxCount(VerifierAdresseFiche::class));

        $verifHandler = self::getContainer()->get(VerifierAdresseFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $verifHandler, new VerifierAdresseFiche($lieu->fiche()->idString()));

        $ligne = $this->connection->fetchAssociative(
            'SELECT latitude, ban_score, ban_ecart, ban_fingerprint, address_fingerprint FROM pim_localisation LIMIT 1',
        );
        self::assertIsArray($ligne);
        self::assertNotNull($ligne['latitude'], 'GPS manquant rempli (enrichissement sûr).');
        self::assertSame(0.93, (float) $ligne['ban_score']);
        self::assertSame(0, (int) $ligne['ban_ecart']);
        self::assertSame($ligne['address_fingerprint'], $ligne['ban_fingerprint'], 'Empreinte capturée après enrichissement.');
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'), 'Aucune transition de workflow.');

        // Le re-passage par IndexFiche ne redéclenche rien : pas de boucle.
        $indexHandler(new IndexFiche($lieu->fiche()->idString()));
        self::assertSame(1, $this->outboxCount(VerifierAdresseFiche::class));
    }

    public function testUnEcartBanAlimenteLesSuggestionsDAdresseDeLEcranQualite(): void
    {
        $lieu = $this->lieuFrancais();
        $code = (string) $lieu->fiche()->code();
        self::getContainer()->set(BanClientInterface::class, new FakeBanClient([
            $code => [
                'score' => 0.9,
                'label' => '24 Rue des Tests 33340 Queyrac',
                'codePostal' => '33340',
                'ville' => 'Queyrac',
                'latitude' => '45.35',
                'longitude' => '-0.92',
                'type' => 'housenumber',
            ],
        ]));

        $verifHandler = self::getContainer()->get(VerifierAdresseFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $verifHandler, new VerifierAdresseFiche($lieu->fiche()->idString()));

        $ligne = $this->connection->fetchAssociative('SELECT ville, latitude, ban_ecart, ban_proposition FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        // Ville divergente : rien n'est écrasé, l'écart part en arbitrage.
        self::assertSame("Fontevraud-l'Abbaye", $ligne['ville']);
        self::assertNull($ligne['latitude'], 'Pas d\'enrichissement sur une adresse divergente.');
        self::assertSame(1, (int) $ligne['ban_ecart']);
        self::assertStringContainsString('Queyrac', (string) $ligne['ban_proposition']);

        $suggestions = self::getContainer()->get(QualiteRepository::class)->suggestionsAdresse();
        self::assertCount(1, $suggestions);
        self::assertSame((int) $code, $suggestions[0]['code']);
        self::assertStringContainsString('Queyrac', (string) $suggestions[0]['proposition']);
    }

    public function testUnResultatVideNeLaisseAucunePropositionAArbitrer(): void
    {
        $lieu = $this->lieuFrancais();
        // Le lot CSV renvoie une ligne pour chaque adresse soumise, même sans
        // correspondance : toutes les colonnes sont vides.
        self::getContainer()->set(BanClientInterface::class, new FakeBanClient([
            (string) $lieu->fiche()->code() => [
                'score' => null,
                'label' => null,
                'name' => null,
                'codePostal' => null,
                'ville' => null,
                'latitude' => null,
                'longitude' => null,
                'type' => null,
            ],
        ]));

        $verifHandler = self::getContainer()->get(VerifierAdresseFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $verifHandler, new VerifierAdresseFiche($lieu->fiche()->idString()));

        $ligne = $this->connection->fetchAssociative('SELECT ban_ecart, ban_proposition FROM pim_localisation LIMIT 1');
        self::assertIsArray($ligne);
        self::assertSame(1, (int) $ligne['ban_ecart']);
        self::assertNull($ligne['ban_proposition'], 'Un résultat vide vaut « aucun résultat fiable », pas une proposition.');
    }

    private function lieuFrancais(): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Abbaye des vérifications');
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeRuePostale('24 Rue des Tests');
        $localisation->changeCodePostal('49590');
        $localisation->changeVille("Fontevraud-l'Abbaye");
        $lieu->changeLocalisation($localisation);
        // Photos conformes aux obligations : IndexFicheHandler rétrograderait
        // sinon la fiche publiée (invariant photos) et fausserait le test.
        for ($i = 0; $i < 4; ++$i) {
            $resource = new RessourceLieu();
            $resource->changeDamAssetId((string) new Ulid());
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeUsage(0 === $i ? 'PHOTO_PRINCIPALE' : 'PHOTO_DIVERSE');
            $resource->changePosition($i + 1);
            $lieu->fiche()->addResource($resource);
        }
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
            'pim_fiche_search',
            'pim_fiche_site_diffusion',
            'pim_fiche_attribute_value',
            'pim_ressource_lieu',
            'pim_fiche_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
