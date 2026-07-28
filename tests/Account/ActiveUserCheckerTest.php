<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\User;
use App\Account\Security\ActiveUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\DisabledException;

final class ActiveUserCheckerTest extends TestCase
{
    public function testActiveAccountPassesChecks(): void
    {
        $user = new User('active@example.com');
        $checker = new ActiveUserChecker();

        $checker->checkPreAuth($user);
        $checker->checkPostAuth($user);

        self::addToAssertionCount(1);
    }

    public function testDisabledAccountIsRejected(): void
    {
        $user = new User('disabled@example.com');
        $user->deactivate();

        $this->expectException(DisabledException::class);
        (new ActiveUserChecker())->checkPreAuth($user);
    }
}
