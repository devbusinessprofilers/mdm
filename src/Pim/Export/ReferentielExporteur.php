<?php

declare(strict_types=1);

namespace App\Pim\Export;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Export Excel de la sélection du référentiel : regroupe les fiches par
 * gamme, charge les agrégats par lots (mémoire bornée, l'EntityManager est
 * purgé entre deux lots) et délègue l'écriture au générateur XLSX.
 */
final readonly class ReferentielExporteur
{
    private const LOT = 100;

    public function __construct(
        private FicheRepository $fiches,
        private FicheImportSchemaRegistry $schemas,
        private FicheExportXlsxGenerator $generateur,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $ids      identifiants ULID de la sélection
     * @param list<string> $colonnes clés cochées du FicheExportColonnesCatalogue
     *
     * @return string chemin du classeur temporaire (à supprimer après envoi)
     */
    public function exporter(array $ids, array $colonnes): string
    {
        $idsParType = [];
        foreach (array_chunk($ids, self::LOT) as $lot) {
            foreach ($this->fiches->findBy(['id' => self::ulids($lot)]) as $fiche) {
                $idsParType[$fiche->type()->value][] = $fiche->idString();
            }
        }
        $this->entityManager->clear();

        $aggregats = [];
        foreach (FicheImportSchemaRegistry::supportedTypes() as $type) {
            if (array_key_exists($type->value, $idsParType)) {
                $aggregats[$type->value] = $this->aggregats($type, $idsParType[$type->value]);
            }
        }

        $chemin = tempnam(sys_get_temp_dir(), 'mdm-export-');
        if (false === $chemin) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire de l\'export.');
        }
        $this->generateur->write($aggregats, $colonnes, $chemin);

        return $chemin;
    }

    public function filename(): string
    {
        return sprintf('referentiel-export-%s.xlsx', (new \DateTimeImmutable())->format('Ymd-Hi'));
    }

    /**
     * @param list<string> $ids
     *
     * @return \Generator<object> agrégats de la gamme, chargés lot par lot
     */
    private function aggregats(TypeFiche $type, array $ids): \Generator
    {
        $schema = $this->schemas->for($type);
        foreach (array_chunk($ids, self::LOT) as $lot) {
            foreach ($this->fiches->findBy(['id' => self::ulids($lot)]) as $fiche) {
                $aggregate = $schema->findAggregateByFiche($fiche);
                if (null !== $aggregate) {
                    yield $aggregate;
                }
            }
            // Lecture seule : purge des entités du lot une fois leurs lignes écrites.
            $this->entityManager->clear();
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Ulid>
     */
    private static function ulids(array $ids): array
    {
        return array_map(static fn (string $id): Ulid => Ulid::fromString($id), $ids);
    }
}
