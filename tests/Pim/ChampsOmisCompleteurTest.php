<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuType;
use App\Pim\Service\ChampsOmisCompleteur;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Soumission partielle de l'éditeur de fiche : un champ vidé côté client
 * (dernière typologie retirée, case décochée, interrupteurs tous éteints)
 * n'est pas envoyé par le navigateur — il doit pourtant être appliqué.
 */
final class ChampsOmisCompleteurTest extends KernelTestCase
{
    public function testLesChampsOmissiblesRendusSontSoumisVides(): void
    {
        $lieu = self::lieu();
        $form = self::form($lieu);

        $data = ChampsOmisCompleteur::completer($form, ['label' => 'Château'], TypeFiche::Lieu);

        self::assertSame('Château', $data['label']);
        self::assertArrayHasKey('generaleTypologie', $data);
        self::assertNull($data['generaleTypologie']);
        self::assertNull($data['businessPremium']);
        self::assertNull($data['disponibilites']['joursOuverture']);
        self::assertNull($data['disponibilites']['dispoLieuPrivatisable']);
        self::assertNull($data['equipementsServices']['equipements']);
        self::assertNull($data['salles']);
        self::assertNull($data['accessibiliteDescription']['pmrAcces']);
        self::assertNull($data['visibilite']['afficherContact']);
        // Champs toujours envoyés par le navigateur : rien à inventer.
        self::assertArrayNotHasKey('generaleWebsiteUrl', $data);
        self::assertArrayNotHasKey('descGenerale', $data['accessibiliteDescription']);
        self::assertArrayNotHasKey('dispoHorairesJours', $data['disponibilites']);
        // Hors écran (feuilles non listées, collection des médias) : intact.
        self::assertArrayNotHasKey('generaleYoutube', $data['visibilite']);
        self::assertArrayNotHasKey('ressources', $data);
        self::assertArrayNotHasKey('restaurant', $data);
    }

    /** Radios Oui/Non (Restaurant) : sans bouton coché, le champ est soumis null → « Non ». */
    public function testLesRadiosOuiNonAbsentesSontSoumisesNulles(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $restaurant = new \App\Pim\Entity\Restaurant\Restaurant();
        $restaurant->changeAccesPmr(true);
        $form = $factory->create(\App\Pim\Form\RestaurantType::class, $restaurant, ['csrf_protection' => false]);

        $data = ChampsOmisCompleteur::completer($form, ['label' => 'Bistrot'], TypeFiche::Restaurant);

        self::assertArrayHasKey('accesPmr', $data);
        self::assertNull($data['accesPmr']);
        self::assertNull($data['typesRestaurant']);
        // Emplacements d'atouts : des champs texte, toujours envoyés par le navigateur.
        self::assertArrayNotHasKey('atouts', $data);
        // Hors écran : intact.
        self::assertArrayNotHasKey('menus', $data);

        $form->submit($data, false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertFalse($restaurant->accesPmr());
    }

    public function testLaDerniereTypologieRetireeEstBienAppliquee(): void
    {
        $lieu = self::lieu();
        $form = self::form($lieu);
        $data = ChampsOmisCompleteur::completer($form, ['label' => 'Château'], TypeFiche::Lieu);

        $form->submit($data, false);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame([], $lieu->generaleTypologie());
        self::assertFalse($lieu->fiche()->businessPremium());
        self::assertSame([], $lieu->joursOuverture());
        self::assertSame([], $lieu->equipements());
        self::assertFalse($lieu->dispoLieuPrivatisable());
        // Non rendu par l'écran : conservé malgré la soumission.
        self::assertSame('https://exemple.test', $lieu->generaleWebsiteUrl());
    }

    /**
     * Les lignes de collection (salles) ne sont créées qu'à la soumission :
     * leurs cases sont complétées d'après le prototype.
     */
    public function testLesCasesDesLignesDeCollectionSontCompletees(): void
    {
        $lieu = self::lieu();
        $form = self::form($lieu);
        $data = ChampsOmisCompleteur::completer($form, [
            'label' => 'Château',
            'generaleTypologie' => ['GENERALE_TYPOLOGIE_1'],
            'salles' => [
                ['nom' => 'Grand salon', 'position' => '0'],
                ['nom' => 'Petit salon', 'position' => '1', 'accesPmr' => '1'],
            ],
        ], TypeFiche::Lieu);

        self::assertNull($data['salles'][0]['lumiereJour']);
        self::assertNull($data['salles'][0]['accesPmr']);
        self::assertSame('1', $data['salles'][1]['accesPmr']);
        self::assertNull($data['salles'][1]['lumiereJour']);
        self::assertArrayNotHasKey('superficie', $data['salles'][0]);

        $form->submit($data, false);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['GENERALE_TYPOLOGIE_1'], $lieu->generaleTypologie());
        $salles = $lieu->salles()->toArray();
        self::assertCount(2, $salles);
        self::assertFalse($salles[0]->accesPmr());
        self::assertTrue($salles[1]->accesPmr());
    }

    private static function lieu(): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_1']);
        $lieu->changeGeneraleWebsiteUrl('https://exemple.test');
        $lieu->fiche()->changeBusinessPremium(true);
        $lieu->changeDispoLieuPrivatisable(true);
        $lieu->changeJoursOuverture(['DISPO_JOUR_OUVERTURE_1']);
        $lieu->changeEquipements(['EQUIPEMENTS_1']);

        return $lieu;
    }

    /** @return FormInterface<mixed> */
    private static function form(Lieu $lieu): FormInterface
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        return $factory->create(LieuType::class, $lieu, ['csrf_protection' => false]);
    }
}
