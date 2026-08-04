<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Repository\AttributDefinitionRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class LovAdminRepositoryTest extends KernelTestCase
{
    public function testFirstPageExecutesWithZeroOffset(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $repository = self::getContainer()->get(AttributDefinitionRepository::class);

        self::assertLessThanOrEqual(50, \count($repository->findAdminPage('', 50, 0)));
    }
}
