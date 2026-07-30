<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Service\ImageRenditionGenerator;
use App\Dam\Service\ImageVariantRegistry;
use PHPUnit\Framework\TestCase;

final class ImageRenditionGeneratorTest extends TestCase
{
    public function testItCreatesEveryExactWebpVariant(): void
    {
        $process = proc_open(['convert', '-size', '1200x800', 'gradient:#2563eb-#f8fafc', 'png:-'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $source = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertIsString($source);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $source);
        rewind($stream);

        $renditions = (new ImageRenditionGenerator())->generate($stream, ['x' => 100, 'y' => 100, 'width' => 900, 'height' => 600], 90);
        fclose($stream);

        self::assertSame(ImageVariantRegistry::names(), array_map(static fn ($rendition): string => $rendition->name, $renditions));
        foreach ($renditions as $rendition) {
            $dimensions = getimagesizefromstring($rendition->contents);
            self::assertIsArray($dimensions);
            self::assertSame(ImageVariantRegistry::all()[$rendition->name]['width'], $dimensions[0]);
            self::assertSame(ImageVariantRegistry::all()[$rendition->name]['height'], $dimensions[1]);
            self::assertSame('image/webp', $dimensions['mime']);
        }
    }
}
