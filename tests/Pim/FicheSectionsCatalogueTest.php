<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Enum\TypeFiche;
use App\Pim\Service\FicheSectionsCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cartes déclaratives de l'éditeur : les cartes ne sont qu'un découpage
 * visuel des champs à plat de la section (source des champs omis, de la
 * complétude et de l'export) — l'invariant garantit qu'aucun champ n'est
 * rendu sans être listé, ni listé sans être rendu.
 */
final class FicheSectionsCatalogueTest extends TestCase
{
    #[DataProvider('gammes')]
    public function testLesCartesCouvrentExactementLesChampsDeLaSection(TypeFiche $type): void
    {
        self::assertNotEmpty(FicheSectionsCatalogue::pour($type));
        foreach (FicheSectionsCatalogue::pour($type) as $index => $section) {
            if (!isset($section['cartes'])) {
                continue;
            }
            $racines = [];
            foreach ($section['cartes'] as $carte) {
                self::assertArrayHasKey('champs', $carte, sprintf('%s section %d', $type->value, $index));
                self::assertContains($carte['colonnes'] ?? 2, [2, 3]);
                foreach ($carte['champs'] as $champ) {
                    $racines[] = explode('.', $champ, 2)[0];
                }
                foreach ($carte['pleins'] ?? [] as $plein) {
                    self::assertContains($plein, $carte['champs']);
                }
                foreach ($carte['conditions'] ?? [] as $cible => $condition) {
                    self::assertContains($cible, array_map(static fn (string $c): string => explode('.', $c)[count(explode('.', $c)) - 1], $carte['champs']));
                    self::assertArrayHasKey('source', $condition);
                    self::assertArrayHasKey('valeurs', $condition);
                }
            }
            self::assertSame(
                array_values(array_unique($racines)),
                array_values(array_unique($section['champs'])),
                sprintf('%s : la section « %s » et ses cartes ne listent pas les mêmes champs.', $type->value, $section['titre']),
            );
        }
    }

    /** @return iterable<string, array{TypeFiche}> */
    public static function gammes(): iterable
    {
        yield 'restaurant' => [TypeFiche::Restaurant];
        yield 'service' => [TypeFiche::ServiceEvenementiel];
        yield 'activite' => [TypeFiche::Activite];
        yield 'lieu' => [TypeFiche::Lieu];
    }

    public function testLesOngletsRestaurantSuiventLaMaquette(): void
    {
        $titres = array_column(FicheSectionsCatalogue::pour(TypeFiche::Restaurant), 'titre');

        self::assertSame(
            ['Informations générales', 'Localisation & accessibilité', 'Description', 'Capacités', 'Services & équipements', 'RSE', 'Tarifs', 'Médias', 'Booster ma visibilité', 'Facturation & partenariat', 'Utilisateurs', 'Templates de message'],
            $titres,
        );
        // Les quatre capacités, jadis absentes de toute section, sont rendues.
        $capacites = FicheSectionsCatalogue::section(TypeFiche::Restaurant, 3);
        self::assertSame(['capaciteAssiseMax', 'capaciteEspacePrivatisable', 'capaciteBanquet', 'capaciteCocktail', 'salles'], $capacites['champs']);
        self::assertSame(7, FicheSectionsCatalogue::indexBloc(TypeFiche::Restaurant, 'medias'));
    }

    public function testLesOngletsServiceSuiventLaMaquette(): void
    {
        $titres = array_column(FicheSectionsCatalogue::pour(TypeFiche::ServiceEvenementiel), 'titre');

        self::assertSame(
            ['Informations générales', 'Localisation & accessibilité', 'Prestations', 'Tarifs', 'Médias', 'Booster ma visibilité', 'Facturation & partenariat', 'Utilisateurs', 'Templates de message'],
            $titres,
        );
        // Les onglets fondus dans Informations générales gardent leurs champs (RSE, description, accessibilité).
        $infos = FicheSectionsCatalogue::section(TypeFiche::ServiceEvenementiel, 0);
        self::assertContains('demarcheRse', $infos['champs']);
        self::assertContains('descriptionGenerale', $infos['champs']);
        self::assertContains('salesforce', $infos['blocs']);
        self::assertSame(['Informations générales', 'Description générale', 'Prestations', 'Matériel'], array_map(static fn (array $c): string => $c['titre'] ?? 'Informations générales', $infos['cartes'] ?? []));
        self::assertContains('acces', FicheSectionsCatalogue::section(TypeFiche::ServiceEvenementiel, 1)['champs']);
    }

    public function testLesOngletsActiviteSuiventLaMaquette(): void
    {
        $titres = array_column(FicheSectionsCatalogue::pour(TypeFiche::Activite), 'titre');

        self::assertSame(
            ['Informations générales', 'Localisation & accessibilité', 'Description', 'Capacités', 'RSE', 'Tarifs', 'Médias', 'Booster ma visibilité', 'Facturation & partenariat', 'Utilisateurs', 'Templates de message'],
            $titres,
        );
        $infos = FicheSectionsCatalogue::section(TypeFiche::Activite, 0);
        self::assertContains('thematiques', $infos['champs']);
        self::assertContains('sousThematiquesNautiquesAquatiques', $infos['champs']);
        // Chaque sous-thématique est conditionnée à sa thématique parente.
        $conditions = $infos['cartes'][0]['conditions'] ?? [];
        self::assertSame(['source' => 'thematiques', 'valeurs' => 'TA_NAUTIQUE_AQUATIQUE'], $conditions['sousThematiquesNautiquesAquatiques']);
        self::assertCount(9, $conditions);
        // Les cartes Localisation fixe / mobile sont conditionnées au rayon d'action.
        $cartes = FicheSectionsCatalogue::section(TypeFiche::Activite, 1)['cartes'] ?? [];
        self::assertSame(['fixe', 'mobile'], [$cartes[1]['condition']['valeurs'], $cartes[2]['condition']['valeurs']]);
        $tarifs = FicheSectionsCatalogue::section(TypeFiche::Activite, 5)['cartes'] ?? [];
        self::assertSame(['forfait', 'option'], [$tarifs[1]['type_offre'], $tarifs[2]['type_offre']]);
    }
}
