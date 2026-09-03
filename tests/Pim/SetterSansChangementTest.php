<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use PHPUnit\Framework\TestCase;

/**
 * Enregistrer une fiche sans rien modifier ne la remet pas « en cours » :
 * un setter qui reçoit la valeur déjà en place ne touche pas la fiche, comme
 * les LOV et les sites de diffusion. Une vraie modification, elle, dépublie.
 */
final class SetterSansChangementTest extends TestCase
{
    public function testLeLieuResteePublieQuandLesValeursSontIdentiques(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Manoir');
        $lieu->changeDescGenerale('Une description.');
        $lieu->changeGeneraleEtabRp(true);
        $lieu->fiche()->publishForImport();

        $lieu->changeLabel('Manoir');
        $lieu->changeDescGenerale('Une description.');
        $lieu->changeGeneraleEtabRp(true);
        $lieu->administratif()->changeInfoLegaleNom(null);
        self::assertSame(StatutFiche::Publiee, $lieu->fiche()->status());

        $lieu->changeDescGenerale('Une autre description.');
        self::assertSame(StatutFiche::EnCours, $lieu->fiche()->status());
    }

    public function testLesAutresGammesSuiventLaMemeRegle(): void
    {
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot');
        $restaurant->changeDescriptionGenerale('Cuisine du marché.');
        $restaurant->fiche()->publishForImport();
        $restaurant->changeDescriptionGenerale('Cuisine du marché.');
        self::assertSame(StatutFiche::Publiee, $restaurant->fiche()->status());
        $restaurant->changeDescriptionGenerale('Cuisine du marché, en terrasse.');
        self::assertSame(StatutFiche::EnCours, $restaurant->fiche()->status());

        $activite = new Activite();
        $activite->changeLabel('Rafting');
        $activite->changeTouteFrance(true);
        $activite->fiche()->publishForImport();
        $activite->changeTouteFrance(true);
        self::assertSame(StatutFiche::Publiee, $activite->fiche()->status());
        $activite->changeTouteFrance(false);
        self::assertSame(StatutFiche::EnCours, $activite->fiche()->status());

        $service = new ServiceEvenementiel();
        $service->changeLabel('Traiteur');
        $service->changeDescriptionGenerale('Buffets.');
        $service->fiche()->publishForImport();
        $service->changeDescriptionGenerale('Buffets.');
        self::assertSame(StatutFiche::Publiee, $service->fiche()->status());
    }
}
