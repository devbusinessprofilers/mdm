<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Service\PasswordResetRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class PasswordResetRateLimiterTest extends TestCase
{
    public function testItLimitsByNormalizedEmailAndIpAddress(): void
    {
        $storage = new InMemoryStorage();
        $email = new RateLimiterFactory([
            'id' => 'email', 'policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour',
        ], $storage);
        $ip = new RateLimiterFactory([
            'id' => 'ip', 'policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour',
        ], $storage);
        $limiter = new PasswordResetRateLimiter($email, $ip);

        self::assertTrue($limiter->accept(' User@Example.com ', '192.0.2.1'));
        self::assertTrue($limiter->accept('user@example.com', '192.0.2.1'));
        self::assertTrue($limiter->accept('USER@EXAMPLE.COM', '192.0.2.1'));
        self::assertFalse($limiter->accept('user@example.com', '192.0.2.1'));

        for ($attempt = 0; $attempt < 6; ++$attempt) {
            self::assertTrue($limiter->accept('another-'.$attempt.'@example.com', '192.0.2.1'));
        }
        self::assertFalse($limiter->accept('last@example.com', '192.0.2.1'));
    }
}
