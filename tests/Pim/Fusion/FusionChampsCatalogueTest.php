<?php

declare(strict_types=1);

namespace App\Tests\Pim\Fusion;

use App\Pim\Entity\Fiche;
use App\Pim\Fusion\FusionChampsCatalogue;
use App\Pim\Fusion\FusionValeurLecteur;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FusionChampsCatalogueTest extends KernelTestCase
{
    public function testChampsComparablesCouvrentLesSchemasSansLesUnionsNiLeCode(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FusionChampsCatalogue::class);

        foreach (FicheImportSchemaRegistry::supportedTypes() as $type) {
            $headers = [];
            foreach ($catalogue->champsComparables($type) as $column) {
                self::assertNotSame('code', $column->header, 'Le code est immuable, il ne se fusionne pas.');
                self::assertNotContains($column->kind, [ColumnKind::LovMulti, ColumnKind::SitesDiffusion], sprintf('%s : %s est une union, pas un choix a/b.', $type->value, $column->header));
                $headers[] = $column->header;
            }
            self::assertSame(count($headers), count(array_unique($headers)), sprintf('En-têtes dupliqués pour %s.', $type->value));
            // Les propriétés portées par la Fiche, absentes des schémas d'import.
            foreach (['business_premium', 'partenaire_bp'] as $supplement) {
                self::assertContains($supplement, $headers, sprintf('Supplément Fiche manquant pour %s.', $type->value));
            }
            foreach ($catalogue->champsUnion($type) as $column) {
                self::assertContains($column->kind, [ColumnKind::LovMulti, ColumnKind::SitesDiffusion]);
            }
        }
    }

    public function testAuditPathsSuiventLaConventionDuSubscriber(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FusionChampsCatalogue::class);

        $prefixes = ['lieu' => 'lieu.', 'activite' => 'activite.', 'restaurant' => 'restaurant.', 'service_evenementiel' => 'service.'];
        foreach (FicheImportSchemaRegistry::supportedTypes() as $type) {
            foreach ($catalogue->champsComparables($type) as $column) {
                $path = $catalogue->auditPath($type, $column);
                self::assertNotNull($path, $column->header);
                if ('label' === $column->target && null === $column->targetPath) {
                    self::assertSame('nom', $path);
                    continue;
                }
                if (in_array($column->targetPath, ['localisation', 'administratif', 'tarification'], true)) {
                    self::assertSame($column->targetPath.'.'.$column->target, $path);
                    continue;
                }
                if (FusionChampsCatalogue::CIBLE_FICHE === $column->targetPath) {
                    self::assertSame('fiche.'.$column->target, $path);
                    self::assertTrue(method_exists(Fiche::class, $column->target), $path);
                    continue;
                }
                self::assertSame(($prefixes[$type->value] ?? self::fail('Préfixe manquant pour '.$type->value)).$column->target, $path);
            }
        }
    }

    public function testLesGettersDeTousLesChampsComparablesExistent(): void
    {
        self::bootKernel();
        $catalogue = self::getContainer()->get(FusionChampsCatalogue::class);
        $schemas = self::getContainer()->get(FicheImportSchemaRegistry::class);
        $lecteur = self::getContainer()->get(FusionValeurLecteur::class);

        foreach (FicheImportSchemaRegistry::supportedTypes() as $type) {
            $aggregate = $schemas->for($type)->createAggregate();
            $fiche = $schemas->for($type)->ficheOf($aggregate);
            foreach ($catalogue->champsComparables($type) as $column) {
                // Jette LogicException si un getter ou un chemin cible manque.
                $lecteur->native($column, $aggregate, $fiche);
                self::assertTrue(method_exists($this->cibleClasse($aggregate, $fiche, $column->targetPath), $column->setter()), sprintf('%s : setter %s absent.', $type->value, $column->setter()));
            }
        }
    }

    private function cibleClasse(object $aggregate, Fiche $fiche, ?string $targetPath): object
    {
        return match ($targetPath) {
            null => $aggregate,
            'localisation' => new \App\Pim\Entity\Localisation(),
            FusionChampsCatalogue::CIBLE_FICHE => $fiche,
            default => $aggregate->{$targetPath}(),
        };
    }
}
