<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Enum\TriReferentiel;
use App\Pim\ReadModel\ReferentielCursor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ReferentielCursorTest extends TestCase
{
    public function testLeCurseurRestitueTriCleEtIdentifiant(): void
    {
        $cursor = new ReferentielCursor(
            TriReferentiel::NomAsc,
            'Château de Chantilly',
            Ulid::fromString('01K1C18C3X5YD4QSVSRQSDFD7Y'),
        );

        $decoded = ReferentielCursor::decode($cursor->encode());

        self::assertNotNull($decoded);
        self::assertSame(TriReferentiel::NomAsc, $decoded->tri);
        self::assertSame('Château de Chantilly', $decoded->cle);
        self::assertSame((string) $cursor->id, (string) $decoded->id);
    }

    public function testLaCleDeModificationEstUneDateValidee(): void
    {
        $cursor = new ReferentielCursor(
            TriReferentiel::ModifDesc,
            '2026-07-29 12:34:56.123456',
            Ulid::fromString('01K1C18C3X5YD4QSVSRQSDFD7Y'),
        );
        $decoded = ReferentielCursor::decode($cursor->encode());
        self::assertNotNull($decoded);
        self::assertSame('2026-07-29 12:34:56.123456', $decoded->cle);

        $this->expectException(\InvalidArgumentException::class);
        ReferentielCursor::decode((new ReferentielCursor(
            TriReferentiel::ModifDesc,
            'pas-une-date',
            Ulid::fromString('01K1C18C3X5YD4QSVSRQSDFD7Y'),
        ))->encode());
    }

    public function testLAncienFormatDeCurseurEstRejete(): void
    {
        // Format d'avant le tri configurable : {u, i} — plus décodable.
        $legacy = rtrim(strtr(base64_encode((string) json_encode([
            'u' => '2026-07-29 12:34:56.123456',
            'i' => '01K1C18C3X5YD4QSVSRQSDFD7Y',
        ])), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        ReferentielCursor::decode($legacy);
    }

    public function testUnTriInconnuEstRejete(): void
    {
        $forge = rtrim(strtr(base64_encode((string) json_encode([
            't' => 'tri_inconnu',
            'k' => 'x',
            'i' => '01K1C18C3X5YD4QSVSRQSDFD7Y',
        ])), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        ReferentielCursor::decode($forge);
    }

    public function testUnCurseurIllisibleEstRejete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReferentielCursor::decode('not-a-valid-cursor');
    }
}
