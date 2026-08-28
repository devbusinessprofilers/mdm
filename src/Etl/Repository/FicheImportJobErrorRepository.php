<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Entity\FicheImportJobError;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheImportJobError> */
final class FicheImportJobErrorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheImportJobError::class);
    }

    /** @return list<FicheImportJobError> */
    public function findPageForJob(FicheImportJob $job, int $limit = 100, int $offset = 0): array
    {
        return $this->findBy(['job' => $job], ['lineNumber' => 'ASC', 'id' => 'ASC'], max(1, min(500, $limit)), max(0, $offset));
    }

    public function countForJob(FicheImportJob $job): int
    {
        return $this->count(['job' => $job]);
    }

    /**
     * Résumé des erreurs groupées par (colonne, message), triées par
     * fréquence décroissante, avec un aperçu des premières lignes touchées —
     * l'écran détail s'ouvre sur ce qui se corrige en masse.
     *
     * @return list<array{columnName: ?string, message: string, occurrences: int, lignes: list<int>}>
     */
    public function summarizeForJob(FicheImportJob $job, int $maxGroups = 20, int $maxLignesApercu = 5): array
    {
        $maxGroups = max(1, min(100, $maxGroups));
        $maxLignesApercu = max(1, min(20, $maxLignesApercu));
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            // Les 5 premiers numéros triés tiennent toujours sous
            // group_concat_max_len : SUBSTRING_INDEX ne lit que le début.
            sprintf(
                'SELECT column_name, message, COUNT(*) AS occurrences,
                        SUBSTRING_INDEX(GROUP_CONCAT(line_number ORDER BY line_number SEPARATOR \',\'), \',\', %d) AS lignes
                 FROM etl_import_job_error
                 WHERE job_id = :jobId
                 GROUP BY column_name, message
                 ORDER BY occurrences DESC, column_name ASC, message ASC
                 LIMIT %d',
                $maxLignesApercu,
                $maxGroups,
            ),
            ['jobId' => $job->id()->toBinary()],
        );

        return array_map(static fn (array $row): array => [
            'columnName' => $row['column_name'],
            'message' => (string) $row['message'],
            'occurrences' => (int) $row['occurrences'],
            'lignes' => array_map(intval(...), explode(',', (string) $row['lignes'])),
        ], $rows);
    }
}
