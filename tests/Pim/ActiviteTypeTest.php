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
                'sousThematiquesSportivesLudiques',
                'sousThematiquesDigitalHighTech',
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
            'sousThematiquesSportivesLudiques' => ['TA_SPORTIVE_LUDIQUE_SS_1'],
            'modeIntervention' => 'mobile',
            'touteFrance' => '1',
            'paysMobiles' => ['FR', 'BE'],
            'regionsMobiles' => ['FR-BRE', 'FR-NOR'],
            'objectifs' => ['OBJECTIF_SEMINAIRE_1'],
            'participantsMin' => '2',
            'participantsMax' => '100',
            'dureeMinMinutes' => '01:00',
            'dureeMaxMinutes' => '02:00',
            'plus' => ['Encadré', 'Clé en main'],
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
        self::assertSame(['FR', 'BE'], $a->paysMobiles());
        self::assertSame(['FR-BRE', 'FR-NOR'], $a->regionsMobiles());
        self::assertSame(['TYPE_EXT_INT_1'], $a->types());
        self::assertSame(['TA_SPORTIVE_LUDIQUE', 'TA_NATURE_RSE'], $a->thematiques());
        self::assertSame(['TA_SPORTIVE_LUDIQUE_SS_1'], $a->sousThematiques());
        self::assertSame(60, $a->dureeMinMinutes());
        self::assertSame(120, $a->dureeMaxMinutes());
        self::assertSame(['Encadré', 'Clé en main'], $a->plus());
        self::assertSame('01:00', $form->createView()['dureeMinMinutes']->vars['value']);

        // Emplacements Forfaits / Options : clé arbitraire, type et position cachés.
        $form = $factory->create(ActiviteType::class, $a, ['csrf_protection' => false]);
        $form->submit(['offres' => [
            'nouveau_forfait_0' => ['type' => 'forfait', 'position' => '0', 'nom' => 'Journée', 'participantsMin' => '4', 'participantsMax' => '20', 'prix' => '90', 'modeTarification' => 'par_personne'],
            // Emplacement vide (décoché) : ignoré, aucune offre créée.
            'nouveau_forfait_1' => ['type' => 'forfait', 'position' => '1', 'nom' => '', 'participantsMin' => '', 'participantsMax' => '', 'prix' => '', 'modeTarification' => 'par_personne'],
            'nouveau_option_1' => ['type' => 'option', 'position' => '1', 'nom' => 'Photographe', 'prix' => '250', 'modeTarification' => 'forfait'],
        ]], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['forfait', 'option'], array_map(static fn ($o): string => $o->type()->value, $a->offres()->toArray()));
        self::assertSame([0, 1], array_map(static fn ($o): int => $o->position(), $a->offres()->toArray()));
    }
}
