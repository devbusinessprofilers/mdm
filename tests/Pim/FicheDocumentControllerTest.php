<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\MediaKind;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Tests\Support\StockageMemoire;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

/**
 * Actions documentaires des fiches de gamme (modifier, remplacer, publier,
 * supprimer, télécharger) : mêmes URL, mêmes noms de formulaires et mêmes
 * réponses pour Activité, Service et Restaurant. Les jetons CSRF sont lus dans
 * le bloc médias, comme le fait le contrôleur Stimulus medias-bloc.
 */
#[Group('database')]
final class FicheDocumentControllerTest extends WebTestCase
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

    /** @return iterable<string, array{string, string}> */
    public static function gammes(): iterable
    {
        yield 'activité' => ['activites', 'activite'];
        yield 'service' => ['services', 'service'];
        yield 'restaurant' => ['restaurants', 'restaurant'];
        // Le Lieu garde ses noms historiques sans préfixe (`document_delete_…`)
        // et sert ses modales à part, préchargées après la page.
        yield 'lieu' => ['lieux', ''];
    }

    #[DataProvider('gammes')]
    public function testLeCycleDeVieDUnDocumentDeGamme(string $gamme, string $prefixe): void
    {
        $client = self::createClient();
        // Le stockage en mémoire doit survivre aux requêtes successives.
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
        self::getContainer()->set(PrivateObjectStorageInterface::class, new StockageMemoire());

        $user = new User('documents-'.$gamme.'@example.test', ['ROLE_BP_VALIDATOR']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);

        $entite = match ($gamme) {
            'activites' => new Activite(),
            'services' => new ServiceEvenementiel(),
            'lieux' => new Lieu(),
            default => new Restaurant(),
        };
        $entite->changeLabel('Fiche à documents');
        $asset = new MediaAsset(new Ulid(), $gamme.'/'.$entite->id().'/plaquette/original.pdf', 'plaquette.pdf', 'application/pdf', 512, str_repeat('a', 64), MediaKind::Document);
        $entityManager->persist($asset);
        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::CommercialSupport);
        $document->changeDamAssetId($asset->id());
        $document->changeLegende('Plaquette 2025');
        $entite->addRessource($document);
        $entityManager->persist($entite);
        $entityManager->flush();
        $client->loginUser($user);

        $base = '/referentiel/'.$gamme.'/fiche/'.$entite->id();
        $documentId = $document->id();

        // Le bloc médias (les modales préchargées pour le Lieu) rend les
        // quatre formulaires de la modale du document.
        $crawler = $client->request('GET', $base.('lieux' === $gamme ? '/medias/modales' : '/medias/bloc'));
        self::assertResponseIsSuccessful();
        $jetons = [];
        foreach (['metadata', 'replace', 'publication', 'delete'] as $action) {
            $nom = ('' === $prefixe ? '' : $prefixe.'_').'document_'.$action.'_'.$documentId;
            $champ = $crawler->filter('input[name="'.$nom.'[_token]"]');
            self::assertCount(1, $champ, sprintf('Le formulaire %s doit être rendu avec son jeton.', $nom));
            $jetons[$action] = (string) $champ->attr('value');
        }
        self::assertSelectorExists('a[href="'.$base.'/documents/'.$documentId.'/download"]');
        $nomMetadata = ('' === $prefixe ? '' : $prefixe.'_').'document_metadata_'.$documentId;
        $nomReplace = ('' === $prefixe ? '' : $prefixe.'_').'document_replace_'.$documentId;
        $nomPublication = ('' === $prefixe ? '' : $prefixe.'_').'document_publication_'.$documentId;
        $nomDelete = ('' === $prefixe ? '' : $prefixe.'_').'document_delete_'.$documentId;

        // Modification des métadonnées.
        $client->xmlHttpRequest('POST', $base.'/documents/'.$documentId.'/modifier', [
            // Seul le formulaire du Lieu porte l'usage (modifiable).
            $nomMetadata => ('lieux' === $gamme ? ['usage' => 'PJ_SUPPORT_COMMERCIAUX'] : []) + ['title' => 'Plaquette 2026', 'source' => 'Prestataire', 'keywords' => 'plaquette', '_token' => $jetons['metadata']],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"ok":true}', (string) $client->getResponse()->getContent());
        // Une seule ressource en base : pas de filtre sur l'id (colonne ULID binaire).
        $ligne = $this->connection->fetchAssociative('SELECT legende, source, keywords FROM pim_ressource_lieu');
        self::assertIsArray($ligne);
        self::assertSame(['legende' => 'Plaquette 2026', 'source' => 'Prestataire', 'keywords' => 'plaquette'], $ligne);

        // Un jeton invalide est refusé sans toucher au document.
        $client->xmlHttpRequest('POST', $base.'/documents/'.$documentId.'/modifier', [
            $nomMetadata => ['title' => 'Pirate', '_token' => 'faux'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Plaquette 2026', $this->connection->fetchOne('SELECT legende FROM pim_ressource_lieu'));

        // La publication d'un document sans droits validés est refusée avec
        // le motif métier (pas une erreur 500).
        $client->xmlHttpRequest('POST', $base.'/documents/'.$documentId.'/publication', [
            $nomPublication => ['_token' => $jetons['publication']],
        ]);
        self::assertResponseStatusCodeSame(422);
        $retour = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($retour);
        self::assertStringContainsString('droits', (string) $retour['error']);

        // Téléchargement : redirection vers l'URL temporaire de l'original.
        $client->request('GET', $base.'/documents/'.$documentId.'/download');
        self::assertResponseRedirects('https://stockage.example.test/'.$asset->originalStorageKey());

        // Remplacement du fichier : nouvel actif DAM, ancien mis en file de suppression.
        $client->xmlHttpRequest(
            'POST',
            $base.'/documents/'.$documentId.'/fichier',
            [$nomReplace => ['_token' => $jetons['replace']]],
            [$nomReplace => ['document' => $this->pdf('plaquette-v2.pdf')]],
        );
        self::assertResponseIsSuccessful();
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dam_media_asset'));
        self::assertNotSame($asset->id(), $this->connection->fetchOne('SELECT dam_asset_id FROM pim_ressource_lieu'));

        // Un document inconnu de la fiche : 404.
        $client->request('GET', $base.'/documents/'.new Ulid().'/download');
        self::assertResponseStatusCodeSame(404);

        // Suppression.
        $client->xmlHttpRequest('POST', $base.'/documents/'.$documentId.'/supprimer', [
            $nomDelete => ['_token' => $jetons['delete']],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_ressource_lieu'));
    }

    public function testLesActionsDocumentairesExigentLeDroitDEdition(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();

        $user = new User('lecteur-documents@example.test', ['ROLE_BP_READER']);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $activite = new Activite();
        $activite->changeLabel('Activité protégée');
        $document = new RessourceLieu();
        $document->configureDocument(DocumentUsage::CommercialSupport);
        $document->changeDamAssetId((string) new Ulid());
        $activite->addRessource($document);
        $entityManager->persist($activite);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('POST', '/referentiel/activites/fiche/'.$activite->id().'/documents/'.$document->id().'/supprimer');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_ressource_lieu'));
    }

    private function pdf(string $nom): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'doc');
        self::assertIsString($chemin);
        file_put_contents($chemin, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($chemin, $nom, 'application/pdf', null, true);
    }

    private function clearTables(): void
    {
        foreach ([
            'pim_ressource_lieu', 'dam_media_duplicate_alert', 'dam_media_rendition', 'dam_media_phash_band', 'dam_media_asset',
            'pim_restaurant_salle', 'pim_restaurant_periode_fermeture', 'pim_restaurant_acces', 'pim_restaurant',
            'pim_activite_offre', 'pim_activite', 'pim_service_evenementiel',
            'pim_acces_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu',
            'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message', 'account_user',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
