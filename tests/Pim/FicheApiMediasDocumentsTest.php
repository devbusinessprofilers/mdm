<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Tests\Support\ClientApiExterne;
use App\Tests\Support\StockageMemoire;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Médias et documents de l'API externe, mêmes gestes pour les quatre gammes :
 * dépôt, métadonnées, ordre, remplacement, publication, téléchargement,
 * suppression — chaque écriture exige If-Match et conserve le statut publié.
 */
#[Group('database')]
final class FicheApiMediasDocumentsTest extends WebTestCase
{
    use ClientApiExterne;

    private const TABLES = [
        'pim_ressource_lieu', 'dam_media_duplicate_alert', 'dam_media_rendition', 'dam_media_phash_band', 'dam_media_asset',
        'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu',
        'pim_restaurant_salle', 'pim_restaurant_periode_fermeture', 'pim_restaurant_acces', 'pim_restaurant',
        'pim_activite_offre', 'pim_activite', 'pim_service_evenementiel',
        'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message',
    ];

    private Connection $connection;
    /** @var list<string> */
    private array $fichiers = [];

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        $this->installerCleJwt();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            foreach (self::TABLES as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }
        foreach ($this->fichiers as $fichier) {
            @unlink($fichier);
        }
        $this->retirerCleJwt();
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function gammes(): iterable
    {
        yield 'lieux' => ['lieux'];
        yield 'restaurants' => ['restaurants'];
        yield 'activites' => ['activites'];
        yield 'services' => ['services'];
    }

    #[DataProvider('gammes')]
    public function testLeCycleDeVieDesMediasEtDesDocuments(string $gamme): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->connection = self::getContainer()->get(Connection::class);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
        self::getContainer()->set(PrivateObjectStorageInterface::class, new StockageMemoire());

        $entite = match ($gamme) {
            'lieux' => new Lieu(),
            'restaurants' => new Restaurant(),
            'activites' => new Activite(),
            default => new ServiceEvenementiel(),
        };
        $entite->changeLabel('Fiche API '.$gamme);
        $salle = null;
        if ($entite instanceof Restaurant) {
            $salle = new RestaurantSalle();
            $salle->changeNom('Salle voûtée');
            $entite->addSalle($salle);
        }
        $entite->fiche()->publishForImport();
        $entityManager->persist($entite);
        $entityManager->flush();
        $id = $entite->id();
        $base = '/api/v1/'.$gamme.'/'.$id;

        // --- Médias -------------------------------------------------------
        $client->request('POST', $base.'/medias', ['usage' => 'PHOTO_DIVERSE', 'legende' => 'Façade'], ['photo' => $this->png('facade.png')], $this->entetesApi($this->ifMatch()));
        self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        $media = $this->jsonApi($client);
        self::assertSame('PHOTO_DIVERSE', $media['usage']);
        self::assertSame('Façade', $media['legende']);
        self::assertSame(0, $media['position']);
        $mediaId = (string) $media['id'];

        // Photo de salle : le Restaurant exige sa salle (dépôt et modification),
        // le Lieu garde son contrat historique (acceptée sans salle).
        if ('restaurants' === $gamme && null !== $salle) {
            $client->request('POST', $base.'/medias', ['usage' => 'CONFIG_PHOTO_SALLE'], ['photo' => $this->png('salle-sans.png')], $this->entetesApi($this->ifMatch()));
            self::assertResponseStatusCodeSame(422);
            self::assertSame('room_required', $this->jsonApi($client)['type']);
            $client->request('POST', $base.'/medias', ['usage' => 'CONFIG_PHOTO_SALLE', 'salleId' => $salle->id()], ['photo' => $this->png('salle.png')], $this->entetesApi($this->ifMatch()));
            self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
            $photoSalle = (string) $this->jsonApi($client)['id'];
            self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_ressource_lieu WHERE usage_code = 'CONFIG_PHOTO_SALLE' AND restaurant_salle_id IS NOT NULL"));
            $client->request('PATCH', $base.'/medias/'.$photoSalle, server: $this->entetesApi($this->ifMatch(['CONTENT_TYPE' => 'application/merge-patch+json'])), content: '{"salleId":""}');
            self::assertResponseStatusCodeSame(422);
            self::assertSame('room_required', $this->jsonApi($client)['type']);
            $client->request('DELETE', $base.'/medias/'.$photoSalle, server: $this->entetesApi($this->ifMatch()));
            self::assertResponseStatusCodeSame(204);
        }
        if ('lieux' === $gamme) {
            $client->request('POST', $base.'/medias', ['usage' => 'CONFIG_PHOTO_SALLE'], ['photo' => $this->png('salle-lieu.png')], $this->entetesApi($this->ifMatch()));
            self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
            $client->request('DELETE', $base.'/medias/'.$this->jsonApi($client)['id'], server: $this->entetesApi($this->ifMatch()));
            self::assertResponseStatusCodeSame(204);
        }

        // Une catégorie inconnue est refusée.
        $client->request('POST', $base.'/medias', ['usage' => 'PHOTO_INCONNUE'], ['photo' => $this->png('autre.png')], $this->entetesApi($this->ifMatch()));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_media_usage', $this->jsonApi($client)['type']);

        $client->request('PATCH', $base.'/medias/'.$mediaId, server: $this->entetesApi($this->ifMatch(['CONTENT_TYPE' => 'application/merge-patch+json'])), content: '{"legende":"Vue principale","keywords":"terrasse"}');
        self::assertResponseIsSuccessful((string) $client->getResponse()->getContent());
        $media = $this->jsonApi($client);
        self::assertSame('Vue principale', $media['legende']);
        self::assertSame('terrasse', $media['keywords']);

        $client->request('PUT', $base.'/medias/ordre', server: $this->entetesApi($this->ifMatch(['CONTENT_TYPE' => 'application/json'])), content: json_encode(['ids' => [$mediaId]], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful((string) $client->getResponse()->getContent());
        self::assertSame('publiee', $this->jsonApi($client)['status']);

        // Sans If-Match : 428, quelle que soit la gamme.
        $client->request('DELETE', $base.'/medias/'.$mediaId, server: $this->entetesApi());
        self::assertResponseStatusCodeSame(428);

        $client->request('DELETE', $base.'/medias/'.$mediaId, server: $this->entetesApi($this->ifMatch()));
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_ressource_lieu WHERE nature = 'photo'"));

        // --- Documents ----------------------------------------------------
        $client->request('POST', $base.'/documents', ['usage' => 'PJ_SUPPORT_COMMERCIAUX', 'title' => 'Plaquette', 'source' => 'Prestataire'], ['document' => $this->pdf('plaquette.pdf')], $this->entetesApi($this->ifMatch()));
        self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        $document = $this->jsonApi($client);
        self::assertSame('PJ_SUPPORT_COMMERCIAUX', $document['usage']);
        self::assertSame('Plaquette', $document['title']);
        self::assertFalse($document['rightsGranted']);
        $documentId = (string) $document['id'];
        $assetId = (string) $document['damAssetId'];

        $client->request('GET', $base.'/documents', server: $this->entetesApi());
        self::assertResponseIsSuccessful();
        $liste = $this->jsonApi($client);
        self::assertCount(1, $liste);
        self::assertSame($documentId, $liste[0]['id']);

        $client->request('PATCH', $base.'/documents/'.$documentId, server: $this->entetesApi($this->ifMatch(['CONTENT_TYPE' => 'application/merge-patch+json'])), content: '{"title":"Plaquette 2026","keywords":"brochure"}');
        self::assertResponseIsSuccessful((string) $client->getResponse()->getContent());
        $document = $this->jsonApi($client);
        self::assertSame('Plaquette 2026', $document['title']);
        self::assertSame('brochure', $document['keywords']);

        // La validation des droits est réservée au PIM.
        $client->request('PATCH', $base.'/documents/'.$documentId, server: $this->entetesApi($this->ifMatch(['CONTENT_TYPE' => 'application/merge-patch+json'])), content: '{"rightsGranted":true}');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('rights_validation_forbidden', $this->jsonApi($client)['type']);

        $client->request('POST', $base.'/documents/'.$documentId.'/fichier', [], ['document' => $this->pdf('plaquette-v2.pdf')], $this->entetesApi($this->ifMatch()));
        self::assertResponseIsSuccessful((string) $client->getResponse()->getContent());
        self::assertNotSame($assetId, $this->jsonApi($client)['damAssetId']);
        // L'ancien fichier reste en base jusqu'au passage du worker (DeleteMedia).
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM dam_media_asset WHERE kind = 'document'"));

        // Publication refusée tant que les droits ne sont pas validés.
        $client->request('POST', $base.'/documents/'.$documentId.'/publication', server: $this->entetesApi($this->ifMatch(['CONTENT_TYPE' => 'application/json'])), content: '{"published":true}');
        self::assertResponseStatusCodeSame(422, (string) $client->getResponse()->getContent());
        self::assertContains($this->jsonApi($client)['type'], ['publication_refused', 'invalid_document']);

        $client->request('GET', $base.'/documents/'.$documentId.'/download', server: $this->entetesApi());
        self::assertResponseIsSuccessful((string) $client->getResponse()->getContent());
        self::assertStringStartsWith('https://stockage.example.test/', (string) $this->jsonApi($client)['downloadUrl']);

        $client->request('DELETE', $base.'/documents/'.$documentId, server: $this->entetesApi($this->ifMatch()));
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_ressource_lieu'));
        // Toutes ces écritures ont conservé le statut publié.
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche'));
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function ifMatch(array $extra = []): array
    {
        return $extra + ['HTTP_IF_MATCH' => '"'.(int) $this->connection->fetchOne('SELECT version FROM pim_fiche').'"'];
    }

    private function png(string $nom): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mdm-api-image-');
        self::assertIsString($path);
        $this->fichiers[] = $path;
        file_put_contents($path, $this->pngApi(960, 480));

        return new UploadedFile($path, $nom, 'image/png', null, true);
    }

    private function pdf(string $nom): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mdm-api-doc-');
        self::assertIsString($path);
        $this->fichiers[] = $path;
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($path, $nom, 'application/pdf', null, true);
    }
}
