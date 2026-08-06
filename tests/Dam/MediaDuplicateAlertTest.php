<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaDuplicateAlert;
use App\Dam\Enum\DuplicateKind;
use App\Dam\Enum\DuplicateReviewStatus;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class MediaDuplicateAlertTest extends TestCase
{
    public function testAcceptedAlertReturnsToPendingWhenTheMatchChanges(): void
    {
        $media = $this->media('candidate.jpg', str_repeat('a', 64));
        $reference = $this->media('reference.jpg', str_repeat('b', 64));
        $replacement = $this->media('replacement.jpg', str_repeat('c', 64));
        $lieu = new Lieu();
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($media->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($resource);

        $alert = new MediaDuplicateAlert($media, $reference, $resource, DuplicateKind::Exact, 0);
        $alert->accept('01K1VALIDATOR0000000000000');
        self::assertSame(DuplicateReviewStatus::Accepted, $alert->status());

        $alert->refresh($replacement, DuplicateKind::Perceptual, 5);

        self::assertSame(DuplicateReviewStatus::Pending, $alert->status());
        self::assertSame(DuplicateKind::Perceptual, $alert->kind());
        self::assertSame(5, $alert->distance());
        self::assertNull($alert->reviewedBy());
    }

    private function media(string $filename, string $checksum): MediaAsset
    {
        return new MediaAsset(new Ulid(), 'originals/'.new Ulid(), $filename, 'image/jpeg', 1024, $checksum);
    }
}
