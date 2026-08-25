<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\FicheEnrichmentScan;
use App\Pim\Enum\SuggestionSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheEnrichmentScan> */
final class FicheEnrichmentScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheEnrichmentScan::class);
    }

    /**
     * Marque un lot de fiches comme scannées par une source (upsert : met à
     * jour scanned_at si la trace existe déjà).
     *
     * @param list<string> $ficheIds identifiants ULID
     */
    public function marquer(array $ficheIds, SuggestionSource $source, \DateTimeImmutable $at): void
    {
        if ([] === $ficheIds) {
            return;
        }
        // scanned_at est stocké une seconde AVANT le départ du run : les colonnes
        // sont en secondes entières, et le filtre incrémental exige
        // scanned_at >= updated_at pour sauter une fiche — une fiche modifiée
        // dans la seconde du départ doit rester à rescanner.
        $at = $at->modify('-1 second');
        $valeurs = [];
        $params = [];
        foreach ($ficheIds as $id) {
            $valeurs[] = '(?, ?, ?)';
            $params[] = Ulid::fromString($id)->toBinary();
            $params[] = $source->value;
            $params[] = $at->format('Y-m-d H:i:s');
        }
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO pim_fiche_enrichment_scan (fiche_id, source, scanned_at) VALUES '.implode(', ', $valeurs)
            .' ON DUPLICATE KEY UPDATE scanned_at = VALUES(scanned_at)',
            $params,
        );
    }
}
