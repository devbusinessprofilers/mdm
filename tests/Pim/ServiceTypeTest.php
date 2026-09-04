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
            "csrf_protection" => false,
        ]);
        self::assertFalse($form->has("code"));

        foreach (
            [
                "prestations",
                "descriptionGenerale",
                "modeIntervention",
                "localisation",
                "paysMobiles",
                "regionsMobiles",
                "departementsMobiles",
                "tarifParPrestation",
                "tarifParPersonne",
                "tarifParJour",
                "tarifParDemiJournee",
                "tarifParHeure",
                "ressources",
                "supportsCommerciaux",
                "acces",
                "accesPmr",
                "materielAdaptePmr",
            ]
            as $field
        ) {
            self::assertTrue($form->has($field), $field);
        }
        // Oui/Non maquette : radios, « Non » coché quand rien n'est renseigné.
        $vue = $form->createView();
        self::assertTrue($vue["surDevis"]->vars["attr"]["data-oui-non"]);
        self::assertTrue($vue["prestataireEsat"]["1"]->vars["checked"]);

        $form->submit([
            "label" => "Scénographie BP",
            "prestations" => ["TS_TECHNIQUE_AUDIOVISUEL"],
            "descriptionGenerale" => "Description simple sans HTML.",
            "modeIntervention" => "mobile",
            "paysMobiles" => "France\nBelgique",
            "regionsMobiles" => "Bretagne\nNormandie",
            "departementsMobiles" => "35\n14",
            "tarifParPrestation" => "500.50",
            "ressources" => [],
            "accesPmr" => "1",
            "acces" => [["type" => "parking", "nom" => "Parking Vinci", "position" => "0"], ["type" => "gare", "nom" => "Gare de Rennes"]],
        ]);

        self::assertTrue(
            $form->isSynchronized(),
            (string) $form->getErrors(true),
        );
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertTrue($service->accesPmr());
        self::assertFalse($service->materielAdaptePmr(), 'Radio absente = Non.');
        self::assertSame(['parking', 'gare'], array_map(static fn ($a): string => $a->type()->value, $service->acces()->toArray()));
        self::assertSame(["France", "Belgique"], $service->paysMobiles());
        self::assertSame(500.5, $service->tarifParPrestation());
    }
}
