<?php

declare(strict_types=1);

namespace App\Tests\Pim\Export;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Export\FicheExportValueReader;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Repository\SiteDiffusionRepository;
use PHPUnit\Framework\TestCase;

enum StatutTest: string
{
    case Ouvert = 'ouvert';
}

final class FicheExportValueReaderTest extends TestCase
{
    private function reader(): FicheExportValueReader
    {
        return new FicheExportValueReader($this->createStub(SiteDiffusionRepository::class));
    }

    private static function fiche(): Fiche
    {
        return (new Lieu())->fiche();
    }

    public function testLaColonneCodeLitLeCodeDeLaFiche(): void
    {
        $fiche = self::fiche();
        $fiche->assignImportedCode(4321);
        $colonne = new ColumnDefinition('code', ColumnKind::Int, 'code');

        self::assertSame([4321], $this->reader()->cellules($colonne, new \stdClass(), $fiche, []));
    }

    public function testLesFormatsSimplesSontCeuxDeLImport(): void
    {
        $porteur = new class {
            public function actif(): bool
            {
                return true;
            }

            public function inactif(): null
            {
                return null;
            }

            public function debut(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-08-27 09:30');
            }

            public function statut(): StatutTest
            {
                return StatutTest::Ouvert;
            }

            /** @return list<string> */
            public function tags(): array
            {
                return ['a', 'b'];
            }
        };
        $fiche = self::fiche();
        $reader = $this->reader();

        self::assertSame(['oui'], $reader->cellules(new ColumnDefinition('actif', ColumnKind::Bool, 'actif'), $porteur, $fiche, []));
        self::assertSame([null], $reader->cellules(new ColumnDefinition('inactif', ColumnKind::Bool, 'inactif'), $porteur, $fiche, []));
        self::assertSame(['2026-08-27'], $reader->cellules(new ColumnDefinition('debut', ColumnKind::Date, 'debut'), $porteur, $fiche, []));
        self::assertSame(['09:30'], $reader->cellules(new ColumnDefinition('debut', ColumnKind::Time, 'debut'), $porteur, $fiche, []));
        self::assertSame(['ouvert'], $reader->cellules(new ColumnDefinition('statut', ColumnKind::Enum, 'statut', enumClass: StatutTest::class), $porteur, $fiche, []));
        self::assertSame(['a|b'], $reader->cellules(new ColumnDefinition('tags', ColumnKind::StringList, 'tags'), $porteur, $fiche, []));
    }

    public function testLesColonnesLovProduisentLesLibelles(): void
    {
        $porteur = new class {
            public function couleur(): string
            {
                return 'ROUGE';
            }

            /** @return list<string> */
            public function couleurs(): array
            {
                return ['ROUGE', 'INCONNU'];
            }

            /** @return list<string> */
            public function vide(): array
            {
                return [];
            }
        };
        $fiche = self::fiche();
        $reader = $this->reader();
        $choices = ['COULEUR' => ['ROUGE' => 'Rouge vif']];

        $mono = new ColumnDefinition('couleur', ColumnKind::LovMono, 'couleur', lovAttribute: 'COULEUR');
        self::assertSame(['Rouge vif'], $reader->cellules($mono, $porteur, $fiche, $choices));

        // Code sans libellé connu : le code reste visible, rien ne se perd.
        $multi = new ColumnDefinition('couleurs', ColumnKind::LovMulti, 'couleurs', lovAttribute: 'COULEUR');
        self::assertSame(['Rouge vif | INCONNU'], $reader->cellules($multi, $porteur, $fiche, $choices));

        $videMulti = new ColumnDefinition('vide', ColumnKind::LovMulti, 'vide', lovAttribute: 'COULEUR');
        self::assertSame([null], $reader->cellules($videMulti, $porteur, $fiche, $choices));
    }

    public function testLaLocalisationAbsenteDonneDesCellulesVides(): void
    {
        $fiche = self::fiche();
        self::assertNull($fiche->localisation());
        $colonne = new ColumnDefinition('localisation_ville', ColumnKind::Text, 'ville', targetPath: 'localisation');

        self::assertSame([null], $this->reader()->cellules($colonne, new \stdClass(), $fiche, []));
    }

    public function testUnGetterAbsentEchoueBruyamment(): void
    {
        $colonne = new ColumnDefinition('fantome', ColumnKind::Text, 'fantome');

        $this->expectException(\LogicException::class);
        $this->reader()->cellules($colonne, new \stdClass(), self::fiche(), []);
    }
}
