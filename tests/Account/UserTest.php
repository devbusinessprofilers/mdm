<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testIdentityIsNormalizedAndRoleUserIsAlwaysGranted(): void
    {
        $user = new User('  Admin@Example.COM ', ['ROLE_SUPER_ADMIN', 'ROLE_SUPER_ADMIN']);
        $user->setPassword('hashed-password');

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $user->id());
        self::assertSame('admin@example.com', $user->email());
        self::assertSame('admin@example.com', $user->getUserIdentifier());
        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertTrue($user->isActive());
        self::assertLessThanOrEqual($user->updatedAt(), $user->createdAt());
    }

    public function testAccountCanBeDisabledAndReenabled(): void
    {
        $user = new User('user@example.com');

        $user->deactivate();
        self::assertFalse($user->isActive());

        $user->activate();
        self::assertTrue($user->isActive());
    }

    public function testAccountCanBeAnonymizedWithoutLosingItsTechnicalIdentity(): void
    {
        $user = new User('person@example.com', ['ROLE_BP_EDITOR']);
        $user->setPassword('hashed-password');
        $id = $user->id();

        $user->anonymize();

        self::assertSame($id, $user->id());
        self::assertSame('deleted+'.strtolower($id).'@invalid.local', $user->email());
        self::assertNull($user->getPassword());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isActive());
        self::assertTrue($user->isDeleted());
        self::assertNotNull($user->deletedAt());

        $this->expectException(\DomainException::class);
        $user->activate();
    }
}
