<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Form\ActiviteType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class ActiviteTypeTest extends KernelTestCase
{
    public function testBibleFieldsUseSymfonyFormTypesAndPopulateTheModel(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $a = new Activite();
        $form = $factory->create(ActiviteType::class, $a, [
            'csrf_protection' => false,
        ]);
        self::assertFalse($form->has('code'));
        foreach (
            [
                'prestataire',
                'types',
                'thematiques',
                'sousThematiques',
                'langues',
                'engagementsRse',
                'modeIntervention',
                'localisation',
                'paysMobiles',
                'regionsMobiles',
                'departementsMobiles',
                'objectifs',
                'offres',
                'ressources',
                'supportsCommerciaux',
            ] as $field
        ) {
            self::assertTrue($form->has($field), $field);
        }
        $form->submit([
            'label' => 'Escape game',
            'types' => ['TYPE_EXT_INT_1'],
            'thematiques' => ['TA_SPORTIVE_LUDIQUE', 'TA_NATURE_RSE'],
            'sousThematiques' => ['TA_SPORTIVE_LUDIQUE_SS_1'],
            'modeIntervention' => 'mobile',
            'touteFrance' => '1',
            'paysMobiles' => "France\nBelgique",
            'regionsMobiles' => "Bretagne\nNormandie",
            'objectifs' => ['OBJECTIF_SEMINAIRE_1'],
            'participantsMin' => '2',
            'participantsMax' => '100',
            'dureeMinMinutes' => '60',
            'dureeMaxMinutes' => '120',
            'plus' => "Encadré\nClé en main",
            'tarifParPersonne' => '25.50',
            'offres' => [],
            'ressources' => [],
        ]);
        self::assertTrue(
            $form->isSynchronized(),
            (string) $form->getErrors(true),
        );
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $errors[] =
                $error->getOrigin()?->getName().': '.$error->getMessage();
        }
        self::assertTrue($form->isValid(), implode('; ', $errors));
        self::assertSame(['France', 'Belgique'], $a->paysMobiles());
        self::assertSame(['Bretagne', 'Normandie'], $a->regionsMobiles());
        self::assertSame(['TYPE_EXT_INT_1'], $a->types());
        self::assertSame(['TA_SPORTIVE_LUDIQUE', 'TA_NATURE_RSE'], $a->thematiques());
        self::assertSame(['TA_SPORTIVE_LUDIQUE_SS_1'], $a->sousThematiques());
    }
}
