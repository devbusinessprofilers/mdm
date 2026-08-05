<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class PasswordResetRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset_email')]
        private RateLimiterFactory $emailLimiter,
        #[Autowire(service: 'limiter.password_reset_ip')]
        private RateLimiterFactory $ipLimiter,
    ) {}

    public function accept(string $email, string $ipAddress): bool
    {
        $emailKey = hash('sha256', User::normalizeEmail($email));
        $emailAccepted = $this->emailLimiter->create($emailKey)->consume()->isAccepted();
        $ipAccepted = $this->ipLimiter->create($ipAddress)->consume()->isAccepted();

        return $emailAccepted && $ipAccepted;
    }
}
