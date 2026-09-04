<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\RestaurantType;
use App\Pim\Service\RestaurantObligationsPublication;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** L'astérisque permanent du Restaurant est le miroir exact des obligations de soumission. */
final class RestaurantObligationsPublicationTest extends KernelTestCase
{
    public function testLesCheminsSontAlignesSurLesViolationsDeSoumission(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        $violations = $validator->validate(new Restaurant(), null, [ValidationGroups::SUBMISSION]);
        $chemins = array_values(array_unique(array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        )));
        // Les photos sont couvertes par la galerie, pas par un champ du formulaire.
        $chemins = array_values(array_diff($chemins, ['ressources']));

        sort($chemins);
        $attendus = RestaurantObligationsPublication::cheminsFormulaire();
        sort($attendus);
        self::assertSame($attendus, $chemins);
        // Plus d'obligation de réponse Oui/Non : « Non » est la valeur par défaut.
        self::assertNotContains('privatisationTotale', $chemins);
        self::assertNotContains('accesPmr', $chemins);
    }

    public function testLeFormulaireMarqueLesChampsObligatoires(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $view = $factory->create(RestaurantType::class, new Restaurant(), ['csrf_protection' => false])->createView();

        self::assertTrue($view['label']->vars['obligatoire']);
        self::assertTrue($view['acces']->vars['obligatoire']);
        self::assertTrue($view['localisation']['pays']->vars['obligatoire']);
        self::assertArrayNotHasKey('obligatoire', $view['localisation']['arrondissement']->vars);
        // Liste indexée : la mention descend sur chaque emplacement rendu.
        self::assertTrue($view['atouts']['0']->vars['obligatoire']);
        self::assertArrayNotHasKey('obligatoire', $view['privatisationTotale']->vars);
        self::assertArrayNotHasKey('obligatoire', $view['tarifForfaitVin']->vars);
    }
}
