<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\Service\ServiceEvenementielObligationsPublication;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** L'astérisque permanent du Service est le miroir des obligations de soumission, rayon d'action compris. */
final class ServiceEvenementielObligationsPublicationTest extends KernelTestCase
{
    public function testLesCheminsSontAlignesSurLesViolationsDeSoumission(): void
    {
        $inconditionnels = array_values(array_diff(
            ServiceEvenementielObligationsPublication::CHEMINS,
            ServiceEvenementielObligationsPublication::CHEMINS_FIXE,
            ServiceEvenementielObligationsPublication::CHEMINS_MOBILE,
        ));
        self::assertSame(self::trie($inconditionnels), $this->violations(new ServiceEvenementiel()));

        $fixe = new ServiceEvenementiel();
        $fixe->changeModeIntervention(ModeInterventionService::Fixe);
        self::assertSame(
            self::trie([...array_diff($inconditionnels, ['modeIntervention']), ...ServiceEvenementielObligationsPublication::CHEMINS_FIXE]),
            $this->violations($fixe),
        );

        $mobile = new ServiceEvenementiel();
        $mobile->changeModeIntervention(ModeInterventionService::Mobile);
        self::assertSame(
            self::trie([...array_diff($inconditionnels, ['modeIntervention']), ...ServiceEvenementielObligationsPublication::CHEMINS_MOBILE]),
            $this->violations($mobile),
        );
        // Plus d'obligation de réponse Oui/Non.
        self::assertNotContains('prestataireEsat', $inconditionnels);
        self::assertNotContains('surDevis', $inconditionnels);
    }

    public function testLeFormulaireMarqueLesChampsObligatoires(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $view = $factory->create(ServiceEvenementielType::class, new ServiceEvenementiel(), ['csrf_protection' => false])->createView();

        self::assertTrue($view['prestations']->vars['obligatoire']);
        self::assertTrue($view['tarifParHeure']->vars['obligatoire']);
        self::assertTrue($view['localisation']['arrondissement']->vars['obligatoire']);
        self::assertTrue($view['regionsMobiles']->vars['obligatoire']);
        self::assertArrayNotHasKey('obligatoire', $view['surDevis']->vars);
        self::assertArrayNotHasKey('obligatoire', $view['acces']->vars);
    }

    /** @return list<string> chemins des violations de soumission, triés, photos exclues */
    private function violations(ServiceEvenementiel $service): array
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $chemins = array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($validator->validate($service, null, [ValidationGroups::SUBMISSION])),
        );

        return self::trie(array_diff($chemins, ['ressources']));
    }

    /**
     * @param array<int, string> $chemins
     *
     * @return list<string>
     */
    private static function trie(array $chemins): array
    {
        $chemins = array_values(array_unique($chemins));
        sort($chemins);

        return $chemins;
    }
}
