<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\DataFixtures\Pim\ActiviteFixtures;
use App\DataFixtures\Pim\LieuFixtures;
use App\DataFixtures\Pim\ServiceFixtures;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DevelopmentDataCommandsTest extends KernelTestCase
{
    public function testDemoGroupContainsEveryImplementedPimDomain(): void
    {
        self::assertContains('pim-demo', LieuFixtures::getGroups());
        self::assertContains('pim-demo', ActiviteFixtures::getGroups());
        self::assertContains('pim-demo', ServiceFixtures::getGroups());

        self::assertContains('pim-lieux', LieuFixtures::getGroups());
        self::assertContains('pim-activites', ActiviteFixtures::getGroups());
        self::assertContains('pim-services', ServiceFixtures::getGroups());
    }

    public function testDestructiveCleanCommandIsUnavailableOutsideDevelopment(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        self::assertFalse($application->has('app:dev:database:clean'));
    }
}
