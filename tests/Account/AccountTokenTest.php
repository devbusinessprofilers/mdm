<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Entity\AccountInvitation;
use App\Account\Entity\PasswordResetRequest;
use App\Account\Entity\User;
use App\Account\Service\InvitationTokenSigner;
use App\Account\Service\PasswordResetTokenSigner;
use PHPUnit\Framework\TestCase;

final class AccountTokenTest extends TestCase
{
    public function testInvitationCanBeInvalidatedAndItsSignatureIsBoundToIt(): void
    {
        $invitation = new AccountInvitation(new User('invitee@example.com'));
        $signer = new InvitationTokenSigner('test-key');
        $signature = $signer->sign($invitation);

        self::assertTrue($invitation->isUsable());
        self::assertTrue($signer->isValid($invitation, $signature));
        self::assertFalse($signer->isValid($invitation, 'invalid'));

        $invitation->invalidate();
        self::assertFalse($invitation->isUsable());
        self::assertNotNull($invitation->invalidatedAt());
    }

    public function testPasswordResetIsSingleUseAndSigned(): void
    {
        $user = new User('user@example.com');
        $user->setPassword('hashed-password');
        $request = new PasswordResetRequest($user);
        $signer = new PasswordResetTokenSigner('test-key');

        self::assertTrue($request->isUsable());
        self::assertTrue($signer->isValid($request, $signer->sign($request)));

        $request->use();
        self::assertFalse($request->isUsable());
        self::assertNotNull($request->usedAt());
    }

    public function testDeletedUserInvalidatesItsTokens(): void
    {
        $user = new User('deleted@example.com');
        $user->setPassword('hashed-password');
        $invitation = new AccountInvitation($user);
        $request = new PasswordResetRequest($user);

        $user->anonymize();

        self::assertFalse($invitation->isUsable());
        self::assertFalse($request->isUsable());
    }
}
