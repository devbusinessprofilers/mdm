<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaDuplicateAlert;
use App\Dam\Enum\DuplicateKind;
use App\Dam\Enum\MediaKind;
use App\Dam\Service\DamDashboardProvider;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class DamDashboardProviderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
        $request = Request::create('/admin/dam');
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get(RequestStack::class)->push($request);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        parent::tearDown();
    }

    public function testEveryDashboardFilterCanBeLoaded(): void
    {
        $provider = self::getContainer()->get(DamDashboardProvider::class);
        self::assertInstanceOf(DamDashboardProvider::class, $provider);

        foreach (['duplicates', 'rights_missing', 'rights_expiring', 'rights_expired', 'failed', 'published_no_photo', 'published_rights', 'orphans', 'missing_renditions', 'images', 'documents'] as $filter) {
            $page = $provider->page($filter, null, 1);
            self::assertSame($filter, $page['filter']);
            self::assertSame(1, $page['page']);
            self::assertArrayHasKey('stats', $page);
            self::assertArrayHasKey('items', $page);
            self::assertArrayHasKey('total', $page);
        }
    }

    public function testExactDuplicateIncludesTheExistingFiche(): void
    {
        $checksum = str_repeat('a', 64);
        $reference = $this->media('reference.jpg', $checksum);
        $candidate = $this->media('candidate.jpg', $checksum);

        $existingLieu = new Lieu();
        $existingLieu->changeLabel('Fiche avec le média existant');
        $existingLieu->addRessource($this->photo($reference));

        $candidateLieu = new Lieu();
        $candidateLieu->changeLabel('Fiche du nouveau doublon');
        $candidateResource = $this->photo($candidate);
        $candidateLieu->addRessource($candidateResource);

        $alert = new MediaDuplicateAlert($candidate, $reference, $candidateResource, DuplicateKind::Exact, 0);
        $this->entityManager->persist($reference);
        $this->entityManager->persist($candidate);
        $this->entityManager->persist($existingLieu);
        $this->entityManager->persist($candidateLieu);
        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        $page = self::getContainer()->get(DamDashboardProvider::class)->page('duplicates', null, 1);
        $item = array_values(array_filter(
            $page['items'],
            static fn (array $item): bool => $item['alert']->id() === $alert->id(),
        ))[0] ?? null;

        self::assertIsArray($item);
        self::assertSame('Fiche avec le média existant', $item['reference_fiche']['fiche_label']);
        self::assertSame('lieu', $item['reference_fiche']['fiche_type']);
        self::assertStringContainsString($existingLieu->id(), $item['reference_fiche']['fiche_url']);
    }

    public function testMediaTabsSplitImagesFromOtherMedia(): void
    {
        $image = $this->media('photo.jpg', str_repeat('b', 64));
        $document = new MediaAsset(new Ulid(), 'originals/'.new Ulid(), 'plan.pdf', 'application/pdf', 2048, str_repeat('c', 64), MediaKind::Document);

        $lieu = new Lieu();
        $lieu->changeLabel('Fiche avec la photo');
        $lieu->addRessource($this->photo($image));

        $this->entityManager->persist($image);
        $this->entityManager->persist($document);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        $provider = self::getContainer()->get(DamDashboardProvider::class);

        $imagesPage = $provider->page('images', null, 1);
        $imageIds = array_map(static fn (array $item): string => $item['asset']->id(), $imagesPage['items']);
        self::assertContains($image->id(), $imageIds);
        self::assertNotContains($document->id(), $imageIds);
        self::assertSame($imagesPage['stats']['images'], $imagesPage['total']);
        $imageItem = array_values(array_filter(
            $imagesPage['items'],
            static fn (array $item): bool => $item['asset']->id() === $image->id(),
        ))[0];
        self::assertSame('Fiche avec la photo', $imageItem['fiche_label']);
        self::assertSame('lieu', $imageItem['fiche_type']);

        $documentsPage = $provider->page('documents', null, 1);
        $documentIds = array_map(static fn (array $item): string => $item['asset']->id(), $documentsPage['items']);
        self::assertContains($document->id(), $documentIds);
        self::assertNotContains($image->id(), $documentIds);
        $documentItem = array_values(array_filter(
            $documentsPage['items'],
            static fn (array $item): bool => $item['asset']->id() === $document->id(),
        ))[0];
        self::assertNull($documentItem['fiche_label']);
        self::assertNull($documentItem['resource']);
    }

    public function testDeletedMediaAreExcludedFromMediaTabs(): void
    {
        $image = $this->media('visible.jpg', str_repeat('d', 64));
        $deleted = $this->media('deleted.jpg', str_repeat('e', 64));
        $deleted->markDeleted();

        $this->entityManager->persist($image);
        $this->entityManager->persist($deleted);
        $this->entityManager->flush();

        $imagesPage = self::getContainer()->get(DamDashboardProvider::class)->page('images', null, 1);
        $imageIds = array_map(static fn (array $item): string => $item['asset']->id(), $imagesPage['items']);

        self::assertContains($image->id(), $imageIds);
        self::assertNotContains($deleted->id(), $imageIds);
    }

    private function media(string $filename, string $checksum): MediaAsset
    {
        $id = new Ulid();

        return new MediaAsset($id, 'originals/'.$id, $filename, 'image/jpeg', 1024, $checksum);
    }

    private function photo(MediaAsset $asset): RessourceLieu
    {
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');

        return $resource;
    }
}
