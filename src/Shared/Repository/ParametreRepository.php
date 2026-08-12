<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use App\Shared\Entity\Parametre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Parametre> */
final class ParametreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parametre::class);
    }

    /** @return list<Parametre> */
    public function tousTriesParNom(): array
    {
        return $this->findBy([], ['nom' => 'ASC']);
    }

    public function parNom(string $nom): ?Parametre
    {
        return $this->findOneBy(['nom' => $nom]);
    }

    /** @return array<string, string> valeurs surchargées, indexées par nom */
    public function surchargesIndexeesParNom(): array
    {
        /** @var list<array{nom: string, valeur: string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.nom', 'p.valeur')
            ->where('p.valeur IS NOT NULL')
            ->getQuery()
            ->getArrayResult();
        $surcharges = [];
        foreach ($rows as $row) {
            $surcharges[$row['nom']] = $row['valeur'];
        }

        return $surcharges;
    }
}
