<?php

declare(strict_types=1);

namespace App\Tests\Pim\Export;

use App\Pim\Enum\TypeFiche;
use App\Pim\Export\FicheExportColonnesCatalogue;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FicheExportColonnesCatalogueTest extends KernelTestCase
{
    public function testChaqueColonneDeChaqueGammeEstRattacheeAUnOnglet(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FicheExportColonnesCatalogue::class);
        $schemas = self::getContainer()->get(FicheImportSchemaRegistry::class);

        foreach (FicheImportSchemaRegistry::supportedTypes() as $type) {
            $groupes = $catalogue->groupesPour($type);
            $titres = array_column($groupes, 'titre');
            self::assertNotContains(
                FicheExportColonnesCatalogue::AUTRES,
                $titres,
                sprintf('Colonnes orphelines pour %s : compléter les rattachements manuels.', $type->value),
            );

            $schema = $schemas->for($type);
            $cles = [];
            foreach ($groupes as $groupe) {
                foreach ($groupe['colonnes'] as $colonne) {
                    $cles[] = $colonne['cle'];
                }
            }
            self::assertSame(count($cles), count(array_unique($cles)), sprintf('Clés dupliquées pour %s.', $type->value));
            // Toutes les colonnes du schéma sauf `code` (toujours exportée), plus une case par collection.
            self::assertCount(count($schema->ficheColumns()) - 1 + count($schema->collections()), $cles);
        }
    }

    public function testLesColonnesRejoignentLOngletDuDetailDeLaFiche(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FicheExportColonnesCatalogue::class);

        $ongletDe = static function (array $groupes, string $cle): ?string {
            foreach ($groupes as $groupe) {
                foreach ($groupe['colonnes'] as $colonne) {
                    if ($cle === $colonne['cle']) {
                        return $groupe['titre'];
                    }
                }
            }

            return null;
        };

        $lieu = $catalogue->groupesPour(TypeFiche::Lieu);
        self::assertSame('Informations générales', $ongletDe($lieu, 'lieu:generale_typologie'));
        self::assertSame('Informations générales', $ongletDe($lieu, 'lieu:generale_gamme_libelle'));
        self::assertSame('Localisation & accessibilité', $ongletDe($lieu, 'lieu:localisation_ville'));
        self::assertSame('Localisation & accessibilité', $ongletDe($lieu, 'lieu:collection:acces'));
        self::assertSame('Réunion', $ongletDe($lieu, 'lieu:collection:salle'));
        self::assertSame('Facturation & partenariat', $ongletDe($lieu, 'lieu:admin_info_legale_nom'));
        self::assertSame('Tarifs', $ongletDe($lieu, 'lieu:tarif_offre_speciale'));
        self::assertSame('Booster ma visibilité', $ongletDe($lieu, 'lieu:attribution_visibilite'));

        $restaurant = $catalogue->groupesPour(TypeFiche::Restaurant);
        self::assertSame('Salles & capacités', $ongletDe($restaurant, 'restaurant:capacite_banquet'));

        $activite = $catalogue->groupesPour(TypeFiche::Activite);
        self::assertSame('Localisation & zone d\'intervention', $ongletDe($activite, 'activite:toute_france'));

        // L'ordre des onglets est celui du détail de la fiche.
        self::assertSame('Informations générales', $lieu[0]['titre']);
        $indexLocalisation = array_search('Localisation & accessibilité', array_column($lieu, 'titre'), true);
        $indexTarifs = array_search('Tarifs', array_column($lieu, 'titre'), true);
        self::assertIsInt($indexLocalisation);
        self::assertIsInt($indexTarifs);
        self::assertLessThan($indexTarifs, $indexLocalisation);
    }

    public function testColonnesRetenuesGardentToujoursLeCodeEtSuiventLesCoches(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FicheExportColonnesCatalogue::class);

        $retenues = $catalogue->colonnesRetenues(TypeFiche::Lieu, ['lieu:label', 'lieu:generale_typologie']);
        $headers = array_map(static fn ($colonne): string => $colonne->header, $retenues);
        self::assertSame(['code', 'label', 'generale_typologie'], $headers);

        self::assertSame([], $catalogue->collectionsRetenues(TypeFiche::Lieu, ['lieu:label']));
        $collections = $catalogue->collectionsRetenues(TypeFiche::Lieu, ['lieu:collection:salle']);
        self::assertCount(1, $collections);
        self::assertSame('salle', $collections[0]->prefix);
    }

    public function testClesPourConcateneLesGammesEnOrdreCanonique(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FicheExportColonnesCatalogue::class);

        // L'ordre demandé ne compte pas : l'ordre canonique des gammes prime.
        $cles = $catalogue->clesPour([TypeFiche::Restaurant, TypeFiche::Lieu]);
        self::assertStringStartsWith('lieu:', $cles[0]);
        $premiersRestaurant = array_values(array_filter($cles, static fn (string $cle): bool => str_starts_with($cle, 'restaurant:')));
        self::assertNotEmpty($premiersRestaurant);
        self::assertSame(
            array_merge($catalogue->clesPour([TypeFiche::Lieu]), $catalogue->clesPour([TypeFiche::Restaurant])),
            $cles,
        );
    }
}
