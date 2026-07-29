<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\ReadModel\FicheCursor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FicheCursorTest extends TestCase
{
    public function testCursorRoundTripPreservesStableSortKeys(): void
    {
        $cursor = new FicheCursor(
            new \DateTimeImmutable('2026-07-29 12:34:56.123456'),
            Ulid::fromString('01K1C18C3X5YD4QSVSRQSDFD7Y'),
        );

        $decoded = FicheCursor::decode($cursor->encode());

        self::assertNotNull($decoded);
        self::assertSame('2026-07-29 12:34:56.123456', $decoded->updatedAt->format('Y-m-d H:i:s.u'));
        self::assertSame((string) $cursor->id, (string) $decoded->id);
    }

    public function testInvalidCursorIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FicheCursor::decode('not-a-valid-cursor');
    }
}
