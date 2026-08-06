<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Import\Legacy\LegacyPhotoCatalog;
use PHPUnit\Framework\TestCase;

final class LegacyPhotoCatalogTest extends TestCase
{
    private LegacyPhotoCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new LegacyPhotoCatalog();
    }

    public function testCategoriesMapToUsagesInPriorityOrder(): void
    {
        $result = $this->catalog->entries($this->photosJson(), 'Hôtel');

        self::assertSame([], $result['skipped']);
        $usages = array_map(static fn (array $entry): string => $entry['usage'], $result['entries']);
        self::assertSame(
            ['PHOTO_PRINCIPALE', 'PHOTO_DIVERSE', 'PHOTO_FACADE', 'PHOTO_CHAMBRE', 'PHOTO_RESTAURATION', 'PHOTO_DIVERSE', 'PHOTO_DIVERSE'],
            $usages,
        );
        self::assertSame(range(0, 6), array_map(static fn (array $entry): int => $entry['position'], $result['entries']));
        self::assertSame('x/master/1.jpg', $result['entries'][0]['path']);
        self::assertSame('x/divers/1.jpg', $result['entries'][6]['path']);
    }

    public function testActiviteUsesOnlyPrincipaleAndDiverse(): void
    {
        $result = $this->catalog->entries($this->photosJson(), 'Idée');

        $usages = array_unique(array_map(static fn (array $entry): string => $entry['usage'], $result['entries']));
        sort($usages);
        self::assertSame(['PHOTO_DIVERSE', 'PHOTO_PRINCIPALE'], $usages);
        self::assertSame('PHOTO_PRINCIPALE', $result['entries'][0]['usage']);
        self::assertCount(1, array_filter($result['entries'], static fn (array $entry): bool => 'PHOTO_PRINCIPALE' === $entry['usage']));
    }

    public function testActiviteIsCappedAtTenPhotos(): void
    {
        $paths = array_map(static fn (int $index): string => sprintf('x/divers/%d.jpg', $index), range(1, 14));
        $result = $this->catalog->entries(json_encode(['divers' => $paths], JSON_THROW_ON_ERROR), 'Idée');
        self::assertCount(10, $result['entries']);
        self::assertCount(4, $result['skipped']);
    }

    public function testServiceUsesFichePresetWithTenPhotosCap(): void
    {
        $result = $this->catalog->entries($this->photosJson(), 'Prestataires de service');
        $usages = array_unique(array_map(static fn (array $entry): string => $entry['usage'], $result['entries']));
        sort($usages);
        self::assertSame(['PHOTO_DIVERSE', 'PHOTO_PRINCIPALE'], $usages);

        $paths = array_map(static fn (int $index): string => sprintf('x/divers/%d.jpg', $index), range(1, 12));
        $capped = $this->catalog->entries(json_encode(['divers' => $paths], JSON_THROW_ON_ERROR), 'Prestataires de service');
        self::assertCount(10, $capped['entries']);
        self::assertCount(2, $capped['skipped']);
    }

    private function photosJson(): string
    {
        return json_encode([
            'divers' => ['x/divers/1.jpg'],
            'master' => ['x/master/1.jpg', 'x/master/2.jpg'],
            'chambre' => ['x/chambre/1.jpg'],
            'facade' => ['x/facade/1.jpg'],
            'restaurant' => ['x/restaurant/1.jpg'],
            'salles_reunion' => ['x/salles/1.jpg'],
        ], JSON_THROW_ON_ERROR);
    }

    public function testLieuIsCappedAtTwentyFivePhotos(): void
    {
        $paths = array_map(static fn (int $index): string => sprintf('x/chambre/%d.jpg', $index), range(1, 30));
        $result = $this->catalog->entries(json_encode(['chambre' => $paths], JSON_THROW_ON_ERROR), 'Lieu');
        self::assertCount(25, $result['entries']);
        self::assertCount(5, $result['skipped']);
        self::assertSame(25, $result['skipped'][0]['position']);
    }

    public function testDuplicatesAndInvalidJsonAreHandled(): void
    {
        $result = $this->catalog->entries('{"master":["a.jpg","a.jpg"],"divers":["a.jpg"]}', 'Lieu');
        self::assertCount(1, $result['entries']);
        self::assertSame([], $this->catalog->entries('pas du json', 'Lieu')['entries']);
    }
}
