<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Form\LieuType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class LieuTypeTest extends KernelTestCase
{
    public function testCompleteBibleSectionsExposeModelAdministrativeAndPricingFields(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        $lieu = new Lieu();
        $form = $factory->create(LieuType::class, $lieu, ['csrf_protection' => false]);

        self::assertFalse($form->has('code'));

        foreach (['informationsGenerales', 'disponibilites', 'accessibiliteDescription', 'hebergement', 'syntheseSalles', 'equipementsServices', 'rse', 'loisirs', 'restauration', 'visibilite', 'administratif', 'tarification'] as $section) {
            self::assertTrue($form->has($section), $section);
        }
        self::assertTrue($form->get('accessibiliteDescription')->has('descGeneralePointInteret'));
        self::assertTrue($form->get('rse')->has('rseDescGenerale'));
        self::assertCount(37, $form->get('administratif'));
        self::assertCount(25, $form->get('tarification'));

        $form->submit([
            'label' => 'Lieu Bible',
            'accessibiliteDescription' => ['descGeneralePointInteret' => 'Musée à proximité'],
            'rse' => ['rseDescGenerale' => 'Engagement complet', 'demarcheRse' => '1', 'esat' => '1'],
            'administratif' => ['infoLegaleNom' => 'Société Bible'],
            'tarification' => ['seminaireJourneeJourneeEtude' => '125.50'],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('Musée à proximité', $lieu->descGeneralePointInteret());
        self::assertSame('Engagement complet', $lieu->rseDescGenerale());
        self::assertTrue($lieu->demarcheRse());
        self::assertTrue($lieu->esat());
        self::assertSame('Société Bible', $lieu->administratif()->infoLegaleNom());
        self::assertSame('125.50', $lieu->tarification()->seminaireJourneeJourneeEtude());
    }

    public function testTemporaryCrudFormCreatesNestedData(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        $lieu = new Lieu();
        $form = $factory->create(LieuType::class, $lieu, ['csrf_protection' => false]);

        $form->submit([
            'label' => 'Lieu test',
            'generaleTypologie' => ['GENERALE_TYPOLOGIE_20'],
            'generaleWebsiteUrl' => 'https://example.test',
            'informationsGenerales' => ['evenementsPredilection' => ['GENERALE_EVENEMENTS_PREDILECTION_1', 'GENERALE_EVENEMENTS_PREDILECTION_5']],
            'disponibilites' => ['joursOuverture' => ['DISPO_JOUR_OUVERTURE_1', 'DISPO_JOUR_OUVERTURE_2']],
            'localisation' => ['pays' => 'France', 'region' => '', 'departement' => '', 'ruePostale' => '1 rue Test', 'codePostal' => '75001', 'ville' => 'Paris', 'arrondissement' => '', 'latitude' => '', 'longitude' => ''],
            'salles' => [['nom' => 'Auditorium', 'superficie' => '100', 'capaciteReunion' => '', 'capaciteU' => '', 'capaciteClasse' => '', 'capaciteTheatre' => '120', 'capaciteCabaret' => '', 'capaciteBanquet' => '', 'capaciteCocktail' => '', 'capaciteAuditorium' => '120', 'lumiereJour' => '1', 'accesPmr' => '1', 'espaceDansant' => '', 'climatisee' => '1', 'position' => '']],
            'periodesFermeture' => [['nom' => 'Fermeture annuelle', 'dateDebut' => '2026-08-01', 'dateFin' => '2026-08-15']],
            'acces' => [['type' => 'gare', 'nom' => 'Gare de Lyon', 'distanceKilometres' => '2.5', 'dureeMinutes' => '10', 'modeTransport' => 'voiture', 'position' => '']],
            'ressources' => [['damAssetId' => 'dam-123', 'nature' => 'document', 'usage' => 'rse', 'legende' => '', 'position' => '']],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('Paris', $lieu->localisation()?->ville());
        self::assertSame(['GENERALE_EVENEMENTS_PREDILECTION_1', 'GENERALE_EVENEMENTS_PREDILECTION_5'], $lieu->evenementsPredilection());
        self::assertSame(['DISPO_JOUR_OUVERTURE_1', 'DISPO_JOUR_OUVERTURE_2'], $lieu->joursOuverture());
        self::assertCount(1, $lieu->salles());
        self::assertCount(1, $lieu->periodesFermeture());
        self::assertCount(1, $lieu->acces());
        self::assertCount(1, $lieu->ressources());
        $salle = $lieu->salles()->first();
        $acces = $lieu->acces()->first();
        $ressource = $lieu->ressources()->first();
        self::assertInstanceOf(Salle::class, $salle);
        self::assertInstanceOf(AccesLieu::class, $acces);
        self::assertInstanceOf(RessourceLieu::class, $ressource);
        self::assertSame(0, $salle->position());
        self::assertSame(0, $acces->position());
        self::assertSame(0, $ressource->position());
    }

    public function testInvalidCoordinateBecomesAFormErrorInsteadOfAnException(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $factory->create(LieuType::class, new Lieu(), ['csrf_protection' => false]);

        $form->submit([
            'label' => 'Lieu avec coordonnée invalide',
            'localisation' => [
                'pays' => 'France', 'countryCode' => 'FR', 'region' => '', 'departement' => '',
                'ruePostale' => '', 'codePostal' => '75001', 'ville' => 'Paris', 'arrondissement' => '',
                'latitude' => '91', 'longitude' => '2.3522',
            ],
        ]);

        self::assertFalse($form->get('localisation')->get('latitude')->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertStringContainsString('coordonnée saisie est invalide', (string) $form->getErrors(true));
    }

    public function testEmptyDamIdentifierDoesNotCauseATypeErrorBeforeUpload(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $factory->create(LieuType::class, new Lieu(), ['csrf_protection' => false]);

        $form->submit([
            'label' => 'Lieu avec nouvelle photo',
            'ressources' => [[
                'nature' => 'photo',
                'usage' => 'PHOTO_DIVERSE',
                'legende' => '',
                'position' => '0',
            ]],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertStringContainsString('Sélectionnez une image', (string) $form->getErrors(true));
    }
}
