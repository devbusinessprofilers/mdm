<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\RestaurantType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class RestaurantTypeTest extends KernelTestCase
{
    public function testBibleFieldsUseSymfonyFormTypesAndExcludeDeferredFields(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $restaurant = new Restaurant();
        $form = $factory->create(RestaurantType::class, $restaurant, [
            'csrf_protection' => false,
        ]);
        self::assertFalse($form->has('code'));

        foreach (
            [
                'lieu',
                'typesRestaurant',
                'typesCuisine',
                'specificitesAlimentaires',
                'typesEvenement',
                'joursOuverture',
                'localisation',
                'acces',
                'salles',
                'services',
                'equipements',
                'engagementsRse',
                'ressources',
                'menus',
                'supportsCommerciaux',
            ] as $field
        ) {
            self::assertTrue($form->has($field), $field);
        }

        self::assertFalse($form->has('typeForfait'));
        self::assertFalse($form->has('nomPersonnalise'));
    }

    /** Oui/Non radio : « Non » par défaut, plus d'état « Non renseigné ». */
    public function testLesOuiNonSontDesRadiosNonParDefaut(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $restaurant = new Restaurant();
        $form = $factory->create(RestaurantType::class, $restaurant, ['csrf_protection' => false]);
        $vue = $form->createView();
        self::assertTrue($vue['privatisationTotale']->vars['expanded']);
        self::assertTrue($vue['privatisationTotale']->vars['attr']['data-oui-non']);
        self::assertSame(['1', '0'], array_map(static fn ($c): string => $c->vars['value'], iterator_to_array($vue['privatisationTotale'])));
        self::assertFalse($vue['privatisationTotale']['0']->vars['checked']);
        self::assertTrue($vue['privatisationTotale']['1']->vars['checked'], 'Non est coché quand rien n\'est renseigné.');

        $form->submit(['privatisationTotale' => '1', 'accesPmr' => null], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertTrue($restaurant->privatisationTotale());
        self::assertFalse($restaurant->accesPmr(), 'Aucun bouton coché = Non.');
        self::assertNull($restaurant->toilettesPmr(), 'Un champ absent de la requête reste intact.');
    }

    /** Atouts en cinq emplacements ; une liste API remplace la liste entière. */
    public function testLesAtoutsSontSaisisEnCinqChampsEtRemplacesParLaListeApi(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $restaurant = new Restaurant();
        $restaurant->changeAtouts(['Terrasse', 'Cave', 'Chef étoilé']);
        $form = $factory->create(RestaurantType::class, $restaurant, ['csrf_protection' => false]);
        $vue = $form->createView();
        self::assertCount(5, $vue['atouts']);
        self::assertSame('Les plus / atouts 1', $vue['atouts']['0']->vars['label']);
        self::assertSame('Cave', $vue['atouts']['1']->vars['value']);
        self::assertSame('', $vue['atouts']['4']->vars['value']);

        $form->submit(['atouts' => ['0' => 'Terrasse', '1' => '', '2' => ' Chef étoilé ', '3' => 'Vue', '4' => '']], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['Terrasse', 'Chef étoilé', 'Vue'], $restaurant->atouts());

        $form = $factory->create(RestaurantType::class, $restaurant, ['csrf_protection' => false]);
        $form->submit(['atouts' => ['Seul']], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['Seul'], $restaurant->atouts());
    }

    /** Tarifs : montant décimal en chaîne, vide = non proposé. */
    public function testLesTarifsSontDesMontantsDecimauxNullables(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $restaurant = new Restaurant();
        $form = $factory->create(RestaurantType::class, $restaurant, ['csrf_protection' => false]);
        foreach (array_keys(RestaurantType::TARIFS) as $tarif) {
            self::assertTrue($form->has($tarif), $tarif);
        }
        $form->submit(['tarifDejeunerAssis' => '45,50', 'tarifForfaitVin' => ''], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('45.50', $restaurant->tarifDejeunerAssis());
        self::assertNull($restaurant->tarifForfaitVin());
    }
}
