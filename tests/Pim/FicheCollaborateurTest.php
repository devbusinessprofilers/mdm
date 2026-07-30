<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\FicheCollaborateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class FicheCollaborateurTest extends TestCase
{
    public function testCollaborateurIsOnlyABusinessContact(): void
    {
        $collaborateur = new FicheCollaborateur(' ADA@Example.COM ', 'Ada', 'Lovelace', 'en');

        self::assertSame('ada@example.com', $collaborateur->email());
        self::assertSame('Ada', $collaborateur->firstName());
        self::assertSame('Lovelace', $collaborateur->lastName());
        self::assertSame('en', $collaborateur->language());
        self::assertArrayNotHasKey(UserInterface::class, (new \ReflectionClass($collaborateur))->getInterfaces());
    }
}
