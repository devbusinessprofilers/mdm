<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Form\ActiviteType;
use App\Pim\Service\ActiviteObligationsPublication;
use App\Pim\Validation\ValidationGroups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** L'astérisque permanent de l'Activité est le miroir des obligations de soumission, rayon d'action compris. */
final class ActiviteObligationsPublicationTest extends KernelTestCase
{
    public function testLesCheminsSontAlignesSurLesViolationsDeSoumission(): void
    {
        $inconditionnels = array_values(array_diff(
            ActiviteObligationsPublication::CHEMINS,
            ActiviteObligationsPublication::CHEMINS_FIXE,
            ActiviteObligationsPublication::CHEMINS_MOBILE,
        ));
        self::assertSame(self::trie($inconditionnels), $this->violations(new Activite()));

        $fixe = new Activite();
        $fixe->changeModeIntervention(ModeInterventionActivite::Fixe);
        self::assertSame(
            self::trie([...array_diff($inconditionnels, ['modeIntervention']), ...ActiviteObligationsPublication::CHEMINS_FIXE]),
            $this->violations($fixe),
        );

        $mobile = new Activite();
        $mobile->changeModeIntervention(ModeInterventionActivite::Mobile);
        self::assertSame(
            self::trie([...array_diff($inconditionnels, ['modeIntervention']), ...ActiviteObligationsPublication::CHEMINS_MOBILE]),
            $this->violations($mobile),
        );
    }

    public function testLeFormulaireMarqueLesChampsObligatoiresEtLeursJumeaux(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $view = $factory->create(ActiviteType::class, new Activite(), ['csrf_protection' => false])->createView();

        self::assertTrue($view['prestataire']->vars['obligatoire']);
        self::assertTrue($view['participantsMin']->vars['obligatoire']);
        self::assertTrue($view['participantsMax']->vars['obligatoire']);
        self::assertTrue($view['dureeMaxMinutes']->vars['obligatoire']);
        self::assertTrue($view['localisation']['ville']->vars['obligatoire']);
        self::assertArrayNotHasKey('obligatoire', $view['localisation']['pays']->vars);
        self::assertTrue($view['regionsMobiles']->vars['obligatoire']);
        self::assertArrayNotHasKey('obligatoire', $view['plus']['0']->vars);
        self::assertArrayNotHasKey('obligatoire', $view['offres']->vars);
    }

    /** @return list<string> chemins des violations de soumission, triés, photos exclues */
    private function violations(Activite $activite): array
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $chemins = array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($validator->validate($activite, null, [ValidationGroups::SUBMISSION])),
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
