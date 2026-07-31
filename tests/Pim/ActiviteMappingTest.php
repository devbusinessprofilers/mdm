<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActiviteMappingTest extends KernelTestCase
{
    public function testActivityAndGenericDamResourceMappings(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $activity = $em->getClassMetadata(Activite::class);
        self::assertSame('pim_activite', $activity->getTableName());
        self::assertTrue($activity->hasAssociation('fiche'));
        $resource = $em->getClassMetadata(RessourceLieu::class);
        self::assertTrue($resource->hasAssociation('fiche'));
        self::assertTrue($resource->hasAssociation('lieu'));
    }
}
