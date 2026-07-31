<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\MediaKind;
use App\Dam\Service\DocumentUploadException;
use App\Dam\Service\LieuDocumentUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Shared\Service\PrivateObjectStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LieuDocumentUploaderTest extends TestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        if (null !== $this->file) {
            @unlink($this->file);
        }
    }

    public function testPdfIsStreamedPrivatelyWithoutImageRenditions(): void
    {
        $storage = new DocumentRecordingObjectStorage();
        $uploader = new LieuDocumentUploader($storage, 'test');
        $asset = $uploader->upload(
            $this->upload('%PDF-1.4 test', 'Présentation commerciale.pdf'),
            new Lieu(),
            DocumentUsage::CommercialSupport,
        );
        self::assertSame(MediaKind::Document, $asset->kind());
        self::assertSame('application/pdf', $asset->mimeType());
        self::assertSame(
            'presentation-commerciale.pdf',
            $asset->originalFilename(),
        );
        self::assertSame('private', $storage->options['visibility'] ?? null);
        self::assertCount(0, $asset->renditions());
    }

    public function testRealMimeMustMatchExtension(): void
    {
        $uploader = new LieuDocumentUploader(
            new DocumentRecordingObjectStorage(),
            'test',
        );
        $this->expectException(DocumentUploadException::class);
        $this->expectExceptionMessage('contenu réel');
        $uploader->upload(
            $this->upload('%PDF-1.4 test', 'mensonge.png'),
            new Lieu(),
            DocumentUsage::GeneralPlan,
        );
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        $this->file = tempnam(sys_get_temp_dir(), 'dam-doc-');
        self::assertIsString($this->file);
        file_put_contents($this->file, $contents);

        return new UploadedFile($this->file, $name, null, null, true);
    }
}
final class DocumentRecordingObjectStorage implements PrivateObjectStorageInterface
{
    public string $contents = '';
    /** @var array<string, mixed> */
    public array $options = [];

    public function write(
        string $key,
        string $contents,
        array $options = [],
    ): void {
        $this->contents = $contents;
        $this->options = $options;
    }

    public function writeStream(
        string $key,
        mixed $stream,
        array $options = [],
    ): void {
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
        return false;
    }

    public function temporaryUrl(
        string $key,
        \DateTimeInterface $expiresAt,
    ): string {
        return 'https://private.example.test';
    }

    public function delete(string $key): void
    {
        $this->contents = '';
    }

    public function deleteDirectory(string $prefix): void
    {
        $this->contents = '';
    }
}
