<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import;

use App\Pim\Entity\Localisation;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde anti-divergence : tout setter public change* des agrégats importables doit être
 * couvert par une colonne du schéma d'import ou explicitement exclu ci-dessous.
 */
final class ImportSchemaCoverageTest extends KernelTestCase
{
    private const GLOBAL_EXCLUSIONS = [
        'changeLocalisation',   // géré via les colonnes localisation_*
        'changePosition',       // position déduite de l'ordre des groupes CSV
        'changeSousThematiquesPour', // accès par thématique : couvert par la colonne à plat sous_thematiques
        'changeSousPrestationsPour', // accès par famille : couvert par la colonne à plat sous_prestations
    ];

    private const CLASS_EXCLUSIONS = [
        \App\Pim\Entity\Lieu\Lieu::class => [
            'changeGeneraleGamme',      // colonne de repli dépréciée
            'changeDemarcheRse',        // déduit du thème « RSE » par le row mapper legacy, pas une colonne CSV
            'changeDispoHorairesJours', // tableau complet — l'import passe par les colonnes horaires_* (changeHoraireJour)
            'changeRestaurant',         // liaison entre fiches saisie dans l'éditeur, inconnue du legacy
        ],
        \App\Pim\Entity\FicheAdministratif::class => [
            'changeModePaiementCarteListe', // couvert par la colonne mode_paiement_carte_liste (Lieu::changeModePaiementCarteListe, même stockage)
        ],
        \App\Pim\Entity\Restaurant\Restaurant::class => [
            'changeLieu',               // liaison entre fiches saisie dans l'éditeur, inconnue du legacy
            'changeHorairesJours',      // tableau complet — l'import passe par les colonnes horaires_* (changeHoraireJour)
        ],
    ];

    public function testEveryPublicSetterIsCoveredByASchemaColumnOrExcluded(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(FicheImportSchemaRegistry::class);

        foreach (FicheImportSchemaRegistry::supportedTypes() as $type) {
            $schema = $registry->for($type);
            $aggregate = $schema->createAggregate();

            /** @var array<class-string, array<string, true>> $covered */
            $covered = [];
            foreach ($schema->ficheColumns() as $column) {
                if ('code' === $column->header) {
                    continue;
                }
                $class = match ($column->targetPath) {
                    null => $aggregate::class,
                    'localisation' => Localisation::class,
                    default => $aggregate->{$column->targetPath}()::class,
                };
                $covered[$class][$column->setter()] = true;
            }
            foreach ($schema->collections() as $collection) {
                foreach ($collection->columns as $column) {
                    $covered[$collection->entryClass][$column->setter()] = true;
                }
            }

            foreach (array_keys($covered) as $class) {
                self::assertIsString($class);
                self::assertTrue(class_exists($class));
                $missing = [];
                foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    $name = $method->getName();
                    if (1 !== preg_match('/^change[A-Z]/', $name)) {
                        continue;
                    }
                    if (isset($covered[$class][$name])
                        || in_array($name, self::GLOBAL_EXCLUSIONS, true)
                        || in_array($name, self::CLASS_EXCLUSIONS[$class] ?? [], true)) {
                        continue;
                    }
                    $missing[] = $name;
                }

                self::assertSame([], $missing, sprintf(
                    'Setters de %s non couverts par le schéma d\'import %s : %s. Ajoutez une colonne ou une exclusion explicite.',
                    $class,
                    $type->value,
                    implode(', ', $missing),
                ));
            }
        }
    }
}
