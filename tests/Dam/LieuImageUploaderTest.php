<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Service\LieuImageUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Shared\Service\PrivateObjectStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LieuImageUploaderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    public function testValidLieuImageIsStreamedToThePrivatePrefix(): void
    {
        $storage = new RecordingObjectStorage();
        $uploader = new LieuImageUploader($storage, 'test');
        $lieu = new Lieu();
        $file = $this->uploadedPng(960, 480, 'façade.png');

        $media = $uploader->upload($file, $lieu);

        self::assertMatchesRegularExpression(
            '#^test/lieux/'.preg_quote($lieu->id(), '#').'/[0-9A-HJKMNP-TV-Z]{26}/original\.png$#',
            $media->originalStorageKey(),
        );
        self::assertSame('façade.png', $media->originalFilename());
        self::assertSame('image/png', $media->mimeType());
        self::assertSame(hash('sha256', $storage->contents), $media->checksum());
        self::assertSame('private', $storage->options['visibility'] ?? null);
        self::assertSame('image/png', $storage->options['ContentType'] ?? null);
        self::assertSame($media->id(), $storage->options['Metadata']['media-id'] ?? null);
        self::assertSame($lieu->id(), $storage->options['Metadata']['lieu-id'] ?? null);
    }

    public function testImageSmallerThanTheCahierDesChargesMinimumIsRejected(): void
    {
        $storage = new RecordingObjectStorage();
        $uploader = new LieuImageUploader($storage, 'test');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('960 × 480');

        $uploader->upload($this->uploadedPng(959, 480, 'trop-petite.png'), new Lieu());
    }

    private function uploadedPng(int $width, int $height, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dam-image-');
        self::assertIsString($path);
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $this->png($width, $height));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function png(int $width, int $height): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $rows = str_repeat("\0".str_repeat("\0", $width * 3), $height);

        $compressed = gzcompress($rows, 9);
        self::assertIsString($compressed);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', $compressed)
            .$chunk('IEND', '');
    }
}

final class RecordingObjectStorage implements PrivateObjectStorageInterface
{
    public string $key = '';
    public string $contents = '';

    /** @var array<string, mixed> */
    public array $options = [];

    public function write(string $key, string $contents, array $options = []): void
    {
        $this->key = $key;
        $this->contents = $contents;
        $this->options = $options;
    }

    public function writeStream(string $key, mixed $stream, array $options = []): void
    {
        $contents = stream_get_contents($stream);
        \PHPUnit\Framework\Assert::assertIsString($contents);
        $this->write($key, $contents, $options);
    }

    public function read(string $key): string
    {
        return $this->contents;
    }

    public function readStream(string $key): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        \PHPUnit\Framework\Assert::assertIsResource($stream);
        fwrite($stream, $this->contents);
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return $key === $this->key;
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
    {
        return 'https://private.example.test/'.rawurlencode($key).'?expires='.$expiresAt->getTimestamp();
    }

    public function delete(string $key): void
    {
        if ($key === $this->key) {
            $this->key = '';
            $this->contents = '';
        }
    }
}
