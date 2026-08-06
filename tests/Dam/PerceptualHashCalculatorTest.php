<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Service\PerceptualHashCalculator;
use PHPUnit\Framework\TestCase;

final class PerceptualHashCalculatorTest extends TestCase
{
    public function testItProducesAStable64BitHash(): void
    {
        $pixels = [];
        for ($x = 0; $x < 32; ++$x) {
            for ($y = 0; $y < 32; ++$y) {
                $pixels[] = ($x * 7 + $y * 3) % 256;
            }
        }

        $calculator = new PerceptualHashCalculator();
        $hash = $calculator->hash($pixels);

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $hash);
        self::assertSame($hash, $calculator->hash($pixels));
    }

    public function testHammingDistanceCountsDifferentBits(): void
    {
        $calculator = new PerceptualHashCalculator();

        self::assertSame(0, $calculator->distance('0123456789abcdef', '0123456789abcdef'));
        self::assertSame(4, $calculator->distance('0000000000000000', '000000000000000f'));
        self::assertSame(64, $calculator->distance('0000000000000000', 'ffffffffffffffff'));
    }

    public function testItCalculatesAHashFromAnImageStream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dam-phash-test-');
        self::assertIsString($path);
        $pixels = '';
        for ($index = 0; $index < 32 * 32; ++$index) {
            $pixels .= chr($index % 256).chr(($index * 3) % 256).chr(($index * 7) % 256);
        }
        file_put_contents($path, "P6\n32 32\n255\n".$pixels);
        $stream = fopen($path, 'rb');
        self::assertIsResource($stream);

        try {
            self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (new PerceptualHashCalculator())->calculate($stream));
        } finally {
            fclose($stream);
            unlink($path);
        }
    }

    public function testItRejectsMalformedInputs(): void
    {
        $calculator = new PerceptualHashCalculator();

        $this->expectException(\InvalidArgumentException::class);
        $calculator->distance('not-a-hash', '0000000000000000');
    }
}
