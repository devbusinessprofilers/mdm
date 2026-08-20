<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Import de fichier par salle depuis la matrice « Salles & capacités » du
 * Restaurant : la vignette poste vers le flux documentaire (usage Plan de
 * salle) comme le fait le contrôleur Stimulus salle-plan.
 */
#[Group('database')]
final class RestaurantDocumentUploadTest extends WebTestCase
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

    public function testImportDeFichierRattacheAUneSalleDepuisLaMatrice(): void
    {
        $client = self::createClient();
        // Sans reboot du kernel entre les requêtes : le stub de stockage posé
        // dans le conteneur doit survivre au GET initial de la page.
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        self::getContainer()->set(PrivateObjectStorageInterface::class, new RestaurantDocumentTestStorage());

        $user = new User('salle-plan-restaurant@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot des salles');
        $salle = new RestaurantSalle();
        $salle->changeNom('Salle voûtée');
        $restaurant->addSalle($salle);
        $entityManager->persist($restaurant);
        $entityManager->flush();
        $client->loginUser($user);

        // La matrice de la fiche porte le contrôleur salle-plan et son jeton :
        // c'est là que le navigateur prend ce que le test rejoue en fetch.
        $crawler = $client->request('GET', '/referentiel/restaurants/fiche/'.$restaurant->id().'?section=4');
        self::assertResponseIsSuccessful();
        $fieldset = $crawler->filter('[data-salle-plan-token-value]');
        self::assertGreaterThan(0, $fieldset->count(), 'La matrice des salles doit porter le contrôleur salle-plan.');
        self::assertSame(
            '/referentiel/restaurants/fiche/'.$restaurant->id().'/documents',
            $fieldset->attr('data-salle-plan-url-value'),
        );
        self::assertSame('restaurant_document_upload', $fieldset->attr('data-salle-plan-formulaire-value'));
        $token = (string) $fieldset->attr('data-salle-plan-token-value');

        // Dépôt d'un plan rattaché à la salle, comme le contrôleur Stimulus.
        $client->request(
            'POST',
            '/referentiel/restaurants/fiche/'.$restaurant->id().'/documents',
            ['restaurant_document_upload' => ['usage' => 'CONFIG_PLAN_SALLE', 'salle' => (string) $salle->id(), '_token' => $token]],
            ['restaurant_document_upload' => ['documents' => [$this->pdf('plan-salle-vue.pdf')]]],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"ok":true}', (string) $client->getResponse()->getContent());
        $ligne = $this->connection->fetchAssociative(
            'SELECT usage_code, nature, restaurant_salle_id FROM pim_ressource_lieu',
        );
        self::assertIsArray($ligne);
        self::assertSame('CONFIG_PLAN_SALLE', $ligne['usage_code']);
        self::assertSame('document', $ligne['nature']);
        self::assertNotNull($ligne['restaurant_salle_id']);

        // Un plan de salle sans salle est refusé.
        $client->request(
            'POST',
            '/referentiel/restaurants/fiche/'.$restaurant->id().'/documents',
            ['restaurant_document_upload' => ['usage' => 'CONFIG_PLAN_SALLE', '_token' => $token]],
            ['restaurant_document_upload' => ['documents' => [$this->pdf('plan-sans-salle.pdf')]]],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
        self::assertResponseStatusCodeSame(422);

        // Le plafond (2 plans par salle) refuse le lot qui le dépasserait.
        $client->request(
            'POST',
            '/referentiel/restaurants/fiche/'.$restaurant->id().'/documents',
            ['restaurant_document_upload' => ['usage' => 'CONFIG_PLAN_SALLE', 'salle' => (string) $salle->id(), '_token' => $token]],
            ['restaurant_document_upload' => ['documents' => [$this->pdf('plan-2.pdf'), $this->pdf('plan-3.pdf')]]],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
        self::assertResponseStatusCodeSame(422);
        $retour = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($retour);
        self::assertStringContainsString('maximal', (string) $retour['error']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_ressource_lieu'));
    }

    private function pdf(string $nom): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'plan');
        self::assertIsString($chemin);
        file_put_contents($chemin, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($chemin, $nom, 'application/pdf', null, true);
    }

    private function clearTables(): void
    {
        foreach ([
            'pim_ressource_lieu', 'dam_media_duplicate_alert', 'dam_media_rendition', 'dam_media_phash_band', 'dam_media_asset',
            'pim_restaurant_salle', 'pim_restaurant_periode_fermeture', 'pim_restaurant_acces', 'pim_restaurant',
            'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message', 'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}

final class RestaurantDocumentTestStorage implements PrivateObjectStorageInterface
{
    public function write(string $key, string $contents, array $options = []): void {}

    public function writeStream(string $key, mixed $stream, array $options = []): void {}

    public function read(string $key): string { return ''; }

    public function readStream(string $key): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        if (false === $stream) { throw new \RuntimeException('Flux temporaire indisponible.'); }

        return $stream;
    }

    public function exists(string $key): bool { return false; }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string { return 'https://private.example.test/'.$key; }

    public function delete(string $key): void {}

    public function deleteDirectory(string $prefix): void {}
}
