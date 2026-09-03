<?php

declare(strict_types=1);

namespace App\Dam\Entity;

use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'dam_media_rendition')]
#[ORM\Index(name: 'IDX_DAM_RENDITION_MEDIA', columns: ['media_id'])]
#[ORM\UniqueConstraint(
    name: 'UNIQ_DAM_RENDITION_MEDIA_NAME',
    columns: ['media_id', 'name'],
),]
#[ORM\UniqueConstraint(
    name: 'UNIQ_DAM_RENDITION_STORAGE_KEY',
    columns: ['storage_key'],
),]
#[ORM\HasLifecycleCallbacks]
final class MediaRendition
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\ManyToOne(inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaAsset $media;
    #[ORM\Column(length: 32)]
    private string $name;
    #[ORM\Column(length: 1024)]
    private string $storageKey;
    #[ORM\Column(length: 100)]
    private string $mimeType = 'image/webp';
    #[ORM\Column]
    private int $width;
    #[ORM\Column]
    private int $height;
    #[ORM\Column(type: 'bigint')]
    private int $sizeBytes;

    public function __construct(
        MediaAsset $media,
        string $name,
        string $storageKey,
        int $width,
        int $height,
        int $sizeBytes,
    ) {
        $this->id = new Ulid();
        $this->media = $media;
        $this->name = $name;
        $this->storageKey = $storageKey;
        $this->width = $width;
        $this->height = $height;
        $this->sizeBytes = $sizeBytes;
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function media(): MediaAsset
    {
        return $this->media;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    /** Nouvelle clé du rendu après reclassement dans le bucket public. */
    public function relocate(string $storageKey): void
    {
        $this->storageKey = $storageKey;
        $this->touch();
    }

    public function refresh(
        string $storageKey,
        int $width,
        int $height,
        int $sizeBytes,
    ): void {
        $this->storageKey = $storageKey;
        $this->width = $width;
        $this->height = $height;
        $this->sizeBytes = $sizeBytes;
        $this->touch();
    }
}
