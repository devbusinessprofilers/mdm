<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Service\LieuObligationsPublication;
use App\Tests\Support\LieuComplet;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LieuObligationsPublicationTest extends KernelTestCase
{
    public function testUnLieuVideListeTousLesChampsObligatoires(): void
    {
        $manquants = $this->service()->manquants(new Lieu());

        self::assertSame([
            'generaleTypologie',
            'accessibiliteDescription.descGenerale',
            'acces.aeroport',
            'acces.gare',
            'hebergement.chambreNbTotal',
            'hebergement.chambreCapaciteTotale',
            'hebergement.chambreDescGenerale',
            'syntheseSalles.salleReunionNbTotal',
            'syntheseSalles.salleReunionCapaciteMaxCocktail',
            'syntheseSalles.salleReunionCapaciteMaxTheatre',
            'syntheseSalles.salleReunionCapaciteMinTheatre',
            'syntheseSalles.salleReunionSurfaceMinReunion',
            'syntheseSalles.salleReunionSurfaceMaxReunion',
            'syntheseSalles.salleReunionDescSalleSeminaire',
            'restauration.restaurantTotal',
            'restauration.restaurantCapaciteAssis',
        ], array_keys($manquants));
        self::assertSame('Typologie', $manquants['generaleTypologie']);
    }

    public function testUnLieuCompletNaAucunManquant(): void
    {
        self::assertSame([], $this->service()->manquants(LieuComplet::completer(new Lieu())));
    }

    public function testLesBlocsHebergementEtSallesNeComptentQueSiCoches(): void
    {
        $lieu = LieuComplet::completer(new Lieu());
        $lieu->changeChambreHebergement(false);
        $lieu->changeChambreNbTotal(null);
        $lieu->changeSalleReunionExist(false);
        $lieu->changeSalleReunionNbTotal(null);

        self::assertSame([], $this->service()->manquants($lieu));
    }

    public function testUnAccesDunAutreTypeNeRemplacePasLaGareNiLaeroport(): void
    {
        $lieu = LieuComplet::completer(new Lieu());
        foreach ($lieu->acces() as $acces) {
            $lieu->removeAcces($acces);
        }
        $metro = new AccesLieu();
        $metro->changeType(TypeAccesLieu::Metro);
        $metro->changeNom('Station');
        $lieu->addAcces($metro);

        self::assertSame(['acces.aeroport', 'acces.gare'], array_keys($this->service()->manquants($lieu)));
    }

    public function testUneDescriptionRicheVideEstConsidereeVide(): void
    {
        $lieu = LieuComplet::completer(new Lieu());
        $lieu->changeDescGenerale('<p>&nbsp; </p>');

        self::assertSame(['accessibiliteDescription.descGenerale'], array_keys($this->service()->manquants($lieu)));
    }

    public function testLeMotifListeLesLibelles(): void
    {
        self::assertSame(
            'Champs obligatoires manquants : Typologie, Texte de description.',
            LieuObligationsPublication::motif(['Typologie', 'Texte de description']),
        );
    }

    private function service(): LieuObligationsPublication
    {
        // LieuRepository est final (non mockable) : le service vient du
        // conteneur, manquants() ne touche pas la base.
        self::bootKernel();

        return self::getContainer()->get(LieuObligationsPublication::class);
    }
}
