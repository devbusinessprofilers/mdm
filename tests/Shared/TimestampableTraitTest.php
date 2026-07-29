<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Entity\TimestampableTrait;
use PHPUnit\Framework\TestCase;

final class TimestampableTraitTest extends TestCase
{
    public function testTimestampsAreInitializedAndTouched(): void
    {
        $entity = new class {
            use TimestampableTrait;

            public function __construct()
            {
                $this->initializeTimestamps();
            }

            public function change(): void
            {
                $this->touch();
            }
        };

        $createdAt = $entity->createdAt();
        $updatedAt = $entity->updatedAt();
        usleep(1_000);
        $entity->change();

        self::assertSame($createdAt, $entity->createdAt());
        self::assertGreaterThan($updatedAt, $entity->updatedAt());
    }
}
