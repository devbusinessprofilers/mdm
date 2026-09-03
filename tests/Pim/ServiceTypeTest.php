<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Form\ServiceEvenementielType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class ServiceTypeTest extends KernelTestCase
{
    public function testV1FieldsUseSymfonyFormTypesAndPopulateTheModel(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $service = new ServiceEvenementiel();
        $form = $factory->create(ServiceEvenementielType::class, $service, [
            'csrf_protection' => false,
        ]);
        self::assertFalse($form->has('code'));

        foreach (
            [
                'prestations',
                'descriptionGenerale',
                'modeIntervention',
                'localisation',
                'paysMobiles',
                'regionsMobiles',
                'departementsMobiles',
                'tarifParPrestation',
                'tarifParPersonne',
                'tarifParJour',
                'tarifParDemiJournee',
                'tarifParHeure',
                'ressources',
                'supportsCommerciaux',
            ] as $field
        ) {
            self::assertTrue($form->has($field), $field);
        }

        $form->submit([
            'label' => 'Scénographie BP',
            'prestations' => ['TS_TECHNIQUE_AUDIOVISUEL'],
            'descriptionGenerale' => 'Description simple sans HTML.',
            'modeIntervention' => 'mobile',
            'paysMobiles' => "France\nBelgique",
            'regionsMobiles' => "Bretagne\nNormandie",
            'departementsMobiles' => "35\n14",
            'tarifParPrestation' => '500.50',
            'ressources' => [],
        ]);

        self::assertTrue(
            $form->isSynchronized(),
            (string) $form->getErrors(true),
        );
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['France', 'Belgique'], $service->paysMobiles());
        self::assertSame(500.5, $service->tarifParPrestation());
    }
}
