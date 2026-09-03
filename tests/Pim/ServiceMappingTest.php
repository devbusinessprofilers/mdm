<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServiceMappingTest extends KernelTestCase
{
    public function testServiceAndGenericDamResourceMappings(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $service = $entityManager->getClassMetadata(ServiceEvenementiel::class);
        self::assertSame('pim_service_evenementiel', $service->getTableName());
        self::assertTrue($service->hasAssociation('fiche'));

        foreach (
            [
                'tarifParPrestation',
                'tarifParPersonne',
                'tarifParJour',
                'tarifParDemiJournee',
                'tarifParHeure',
            ] as $field
        ) {
            self::assertSame('float', $service->getTypeOfField($field));
        }

        $resource = $entityManager->getClassMetadata(RessourceLieu::class);
        self::assertTrue($resource->hasAssociation('fiche'));
    }
}
